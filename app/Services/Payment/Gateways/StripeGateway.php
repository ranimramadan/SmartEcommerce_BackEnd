<?php

namespace App\Services\Payment\Gateways;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentIntent;
use App\Models\PaymentProvider;
use App\Services\Payment\Contracts\PaymentGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Stripe\Stripe;
use Stripe\Webhook;
use Stripe\PaymentIntent as StripePI;
use Stripe\Refund as StripeRefund;

class StripeGateway implements PaymentGateway
{
    public function code(): string { return 'stripe'; }

    protected function boot(PaymentProvider $provider): void
    {
        $secret = $provider->getConfigValue('secret_key') ?: config('services.stripe.secret');
        if (!$secret) {
            throw new \RuntimeException('Stripe secret key is missing (provider.config.secret_key or services.stripe.secret).');
        }
        Stripe::setApiKey($secret);
    }

    public function createIntent(Order $order, PaymentProvider $provider): ?PaymentIntent
    {
        $this->boot($provider);

        $pi = StripePI::create([
            'amount'                   => (int) round($order->grand_total * 100),
            'currency'                 => strtolower($order->currency),
            'metadata'                 => ['order_id' => $order->id, 'order_number' => $order->order_number],
            'automatic_payment_methods'=> ['enabled' => true],
        ]);

        return PaymentIntent::create([
            'order_id'             => $order->id,
            'payment_provider_id'  => $provider->id ?? null,
            'provider_payment_id'  => $pi->id,
            'client_secret'        => $pi->client_secret,
            'idempotency_key'      => (string) Str::uuid(),
            'status'               => $pi->status,
            'amount'               => $order->grand_total,
            'currency'             => $order->currency,
            'meta'                 => ['raw' => $pi->toArray()],
        ]);
    }

    public function confirm(Order $order, PaymentProvider $provider, ?string $payloadId = null): ?Payment
    {
        // عادةً confirmation يتم بالفرونت والـ capture بالويبهوك
        return null;
    }

    public function refund(Payment $payment, int|float $amount, ?string $reason = null): bool
    {
        // لا نسمح إلا باسترجاع كامل
        if (bccomp((string)$amount, (string)$payment->amount, 2) !== 0) {
            return false;
        }

        $this->boot($payment->provider);

        // استرجاع كامل (نرسل amount أيضًا لا بأس)
        $res = StripeRefund::create([
            'payment_intent' => $payment->transaction_id, // خزّنتِ PI ID كـ transaction_id
            'amount'         => (int) round($amount * 100),
            'reason'         => $reason ?: 'requested_by_customer',
        ]);

        $payment->refunds()->create([
            'order_id'           => $payment->order_id,
            'amount'             => $amount,
            'status'             => $res->status === 'succeeded' ? 'succeeded' : 'pending',
            'reason'             => $reason,
            'provider_refund_id' => $res->id,
        ]);

        if ($res->status === 'succeeded') {
            $payment->status = Payment::STATUS_REFUNDED;
            $payment->save();
            $payment->order->update(['payment_status' => 'refunded']);
            return true;
        }
        return false;
    }

    public function canHandleWebhook(Request $request): bool
    {
        return $request->header('Stripe-Signature') !== null;
    }

    public function handleWebhook(Request $request): void
    {
        $endpointSecret = config('services.stripe.webhook_secret');

        if ($endpointSecret) {
            try {
                $event = Webhook::constructEvent(
                    $request->getContent(),
                    $request->header('Stripe-Signature'),
                    $endpointSecret
                );
            } catch (\Throwable $e) {
                abort(400, 'Invalid signature');
            }
        } else {
            // تطوير فقط
            $event = json_decode($request->getContent());
        }

        switch ($event->type) {
            case 'payment_intent.succeeded':
                $this->markSucceeded($event->data->object);
                break;
            case 'payment_intent.payment_failed':
                $this->markFailed($event->data->object);
                break;
            // يمكنك إضافة charge.refunded لاحقًا إن رغبتِ
        }
    }

    protected function markSucceeded($pi): void
    {
        /** @var PaymentIntent|null $intent */
        $intent = PaymentIntent::where('provider_payment_id', $pi->id)->first();
        if (!$intent) return;

        $intent->update(['status' => 'succeeded']);

        $order = $intent->order;
        if (!$order) return;

        // Idempotency: لا تنشئي Payment مكرر
        $exists = Payment::where('payment_provider_id', $intent->payment_provider_id)
            ->where('transaction_id', $pi->id)
            ->exists();

        if (!$exists) {
            $order->payments()->create([
                'payment_provider_id' => $intent->payment_provider_id,
                'idempotency_key'     => $intent->idempotency_key,
                'transaction_id'      => $pi->id,
                'status'              => Payment::STATUS_CAPTURED,
                'amount'              => $intent->amount,
                'currency'            => $intent->currency,
                'raw_response'        => ['pi' => $pi],
            ]);
        }

        // لا تكتب "paid" فوق "refunded"
        if ($order->payment_status !== 'refunded') {
            $order->update(['payment_status' => 'paid']);
        }
    }

    protected function markFailed($pi): void
    {
        $intent = PaymentIntent::where('provider_payment_id', $pi->id)->first();
        if (!$intent) return;

        $intent->update(['status' => 'failed']);

        if ($intent->order) {
            $intent->order->update(['payment_status' => 'failed']);
        }
    }

    public function frontendPayload(?PaymentIntent $intent): array
    {
        return [
            'type'          => 'stripe',
            'client_secret' => $intent?->client_secret,
        ];
    }
}
