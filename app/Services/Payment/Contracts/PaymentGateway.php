<?php

namespace App\Services\Payment\Contracts;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentIntent;
use App\Models\PaymentProvider;
use Illuminate\Http\Request;

interface PaymentGateway
{
    public function code(): string; // 'cod' | 'stripe' ...

    /** إنشاء intent/تجهيز الدفع (إن لزم) وإرجاع PaymentIntent أو null للـ COD */
    public function createIntent(Order $order, PaymentProvider $provider): ?PaymentIntent;

    /** تأكيد/التقاط الدفع يدويًا إن لزم (بعض المزودين يعتمدوا webhook بداله) */
    public function confirm(Order $order, PaymentProvider $provider, ?string $payloadId = null): ?Payment;

    /** إنشاء Refund عند المزود وتحديث داتا التطبيق */
    public function refund(Payment $payment, int|float $amount, ?string $reason = null): bool;

    /** هل هذا المزود يتعامل مع هذا الويبهوك؟ */
    public function canHandleWebhook(Request $request): bool;

    /** معالجة الويبهوك وتحديث models */
    public function handleWebhook(Request $request): void;

    /** بيانات يُظهرها الفرونت (مثلاً client_secret للـ Stripe) */
    public function frontendPayload(?PaymentIntent $intent): array;
}
