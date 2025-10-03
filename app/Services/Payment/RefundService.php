<?php

namespace App\Services\Payment; 


use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Services\Payment\Contracts\PaymentGateway;
use App\Services\Payment\GatewayManager;
use Illuminate\Support\Facades\DB;

class RefundService
{
    public function __construct(private GatewayManager $gateways) {}

    public function refundPayment(Payment $payment, float $amount, ?string $reason = null): Refund
    {
        return DB::transaction(function () use ($payment, $amount, $reason) {
            // تحقق: ما نتجاوز المقبوض ناقص مجاميع الاسترداد السابقة
            $refundedSoFar = (float) $payment->refunds()->sum('amount');
            $captured = (float) $payment->amount;
            if ($amount <= 0 || ($refundedSoFar + $amount) > $captured) {
                throw new \InvalidArgumentException('Invalid refund amount.');
            }

            /** @var PaymentGateway $gw */
            $gw = $this->gateways->for($payment->provider?->code ?? 'cod');

            $ok = $gw->refund($payment, $amount, $reason);

            // لو الـ gateway رجّع false، بنسجل pending محليًا (اختياري)
            if (!$ok) {
                return $payment->refunds()->create([
                    'order_id' => $payment->order_id,
                    'amount'   => $amount,
                    'status'   => 'pending',
                    'reason'   => $reason,
                ]);
            }

            // الـ refund تُسجّل داخل gateway عادةً (انظري CodGateway/StripeGateway فوق)
            return $payment->refunds()->latest('id')->first();
        });
    }
}
