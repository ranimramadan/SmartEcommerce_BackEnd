<?php
namespace App\Services\Payment;

use App\Models\{Order, Payment, PaymentIntent, PaymentProvider};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function __construct(private GatewayManager $gateways) {}

    public function startPayment(Order $order, string $providerCode): array
    {
        $gateway = $this->gateways->driver($providerCode);

        return DB::transaction(function () use ($order,$gateway,$providerCode) {

            $intentData = $gateway->createIntent($order);

            $pi = PaymentIntent::create([
                'order_id'            => $order->id,
                'payment_provider_id' => $order->payment_provider_id,
                'provider_payment_id' => $intentData['provider_payment_id'] ?? null,
                'client_secret'       => $intentData['client_secret'] ?? null,
                'idempotency_key'     => $intentData['idempotency'] ?? null,
                'status'              => $intentData['status'] ?? 'requires_payment_method',
                'amount'              => $intentData['amount'] ?? $order->grand_total,
                'currency'            => $intentData['currency'] ?? $order->currency,
                'meta'                => $intentData,
            ]);

            return [
                'payment_intent_id' => $pi->provider_payment_id,
                'client_secret'     => $pi->client_secret,
                'amount'            => $pi->amount,
                'currency'          => $pi->currency,
            ];
        });
    }

    /** يُستدعى من Route الـ webhook */
    public function handleWebhook(string $providerCode, Request $request): void
    {
        $gateway = $this->gateways->driver($providerCode);
        $event   = $gateway->parseWebhook($request);
        $providerPaymentId = $event['provider_payment_id'] ?? null;
        $idempotency       = $event['idempotency'] ?? null;
        $type              = $event['type'] ?? '';

        // منع التكرار
        if ($idempotency && Payment::where('idempotency_key',$idempotency)->exists()) {
            return;
        }

        // جد الطلب/الـ PaymentIntent
        $pi = PaymentIntent::where('provider_payment_id',$providerPaymentId)->first();
        $order = $pi?->order;

        if (! $order) return;

        DB::transaction(function () use ($order,$event,$idempotency) {

            // سجّل دفعة
            $payment = Payment::create([
                'order_id'            => $order->id,
                'payment_provider_id' => $order->payment_provider_id,
                'idempotency_key'     => $idempotency,
                'transaction_id'      => $event['txn_id'] ?? $event['provider_payment_id'] ?? null,
                'status'              => 'captured',
                'amount'              => $order->grand_total,
                'currency'            => $order->currency,
                'raw_response'        => $event['payload'] ?? [],
            ]);

            // حدّث حالة الطلب
            $order->update(['payment_status'=>'paid']);

            // أنشئ الفاتورة تلقائيًا
            app(\App\Services\InvoiceService::class)->createFromOrder($order);

            // ثبّت استخدام الكوبون على مستوى الطلب
            app(\App\Services\CouponService::class)->convertCartRedemptionToOrder($order);
        });
    }
}
