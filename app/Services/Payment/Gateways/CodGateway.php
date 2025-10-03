<?php

namespace App\Services\Payment\Gateways;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentIntent;
use App\Models\PaymentProvider;
use App\Services\Payment\Contracts\PaymentGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CodGateway implements PaymentGateway
{
    public function code(): string { return 'cod'; }

    public function createIntent(Order $order, PaymentProvider $provider): ?PaymentIntent
    {
        // لا تنشئ أكثر من AUTHORIZED لنفس الطلب
        $existingAuth = $order->payments()
            ->where('status', Payment::STATUS_AUTHORIZED)
            ->exists();

        if (!$existingAuth) {
            Payment::create([
                'order_id'             => $order->id,
                'payment_provider_id'  => $provider->id ?? null,
                'idempotency_key'      => (string) Str::uuid(),
                'transaction_id'       => null,
                'status'               => Payment::STATUS_AUTHORIZED,
                'amount'               => $order->grand_total,
                'currency'             => $order->currency,
                'raw_response'         => ['note' => 'COD authorized'],
            ]);
        }

        // لا تكتب authorized فوق paid/refunded
        if (!in_array($order->payment_status, ['paid', 'refunded'], true)) {
            $order->update(['payment_status' => 'authorized']);
        }

        return null; // COD ما عنده Intent خارجي
    }

    public function confirm(Order $order, PaymentProvider $provider, ?string $payloadId = null): ?Payment
    {
        // التقط فقط أحدث AUTHORIZED
        $payment = $order->payments()
            ->where('status', Payment::STATUS_AUTHORIZED)
            ->latest('id')
            ->first();

        if (!$payment) {
            return null;
        }

        if ($payment->status !== Payment::STATUS_CAPTURED) {
            $payment->markAsCaptured();
            // لا تكتب paid فوق refunded
            if ($order->payment_status !== 'refunded') {
                $order->update(['payment_status' => 'paid']);
            }
        }

        return $payment;
    }

    public function refund(Payment $payment, int|float $amount, ?string $reason = null): bool
    {
        // لا نسمح إلا باسترجاع كامل
        if (bccomp((string)$amount, (string)$payment->amount, 2) !== 0) {
            return false;
        }

        $payment->refunds()->create([
            'order_id'           => $payment->order_id,
            'amount'             => $amount,
            'status'             => 'succeeded',
            'reason'             => $reason,
            'provider_refund_id' => null,
        ]);

        $payment->status = Payment::STATUS_REFUNDED;
        $payment->save();

        $payment->order->update(['payment_status' => 'refunded']);
        return true;
    }

    public function canHandleWebhook(Request $request): bool
    {
        return false; // لا ويبهوك لـ COD
    }

    public function handleWebhook(Request $request): void
    {
        // لا شيء
    }

    public function frontendPayload(?PaymentIntent $intent): array
    {
        return [
            'type'    => 'cod',
            'message' => 'You will pay on delivery.',
        ];
    }
}
