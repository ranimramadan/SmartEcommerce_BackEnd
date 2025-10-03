<?php
namespace App\Services;

use App\Models\{Coupon, Cart, Order, CouponRedemption};
use Illuminate\Validation\ValidationException;

class CouponService
{
    public function assertValidForCart(Coupon $coupon, Cart $cart): void
    {
        if (! $coupon->isValidForCart($cart)) {
            throw ValidationException::withMessages(['coupon' => 'Invalid or not applicable coupon.']);
        }
    }

    public function applyOnOrderSnapshot(?Coupon $coupon, Order $order): void
    {
        if (! $coupon) return;

        // الأساس (عدّليه لو عندك منطق أدق)
        $baseForDiscount = (float) $order->subtotal;

        $discount = (float) $coupon->calculateDiscount($baseForDiscount);
        if ($discount < 0) $discount = 0.0;

        $order->coupon_code          = $coupon->code;
        $order->coupon_type          = $coupon->type;
        $order->coupon_value         = $coupon->value;
        $order->coupon_max_discount  = $coupon->max_discount;
        $order->coupon_free_shipping = ($coupon->type === 'free_shipping');
        $order->coupon_discount      = $discount;

        $order->discount_total = (float) $order->discount_total + $discount;

        if ($order->coupon_free_shipping) {
            $order->shipping_total = 0.0;
        }

        $order->save();
    }

    public function recordRedemption(
        Coupon $coupon,
        ?int $userId,
        ?Cart $cart = null,
        ?Order $order = null,
        float $amount = 0
    ): CouponRedemption {
        return CouponRedemption::create([
            'coupon_id' => $coupon->id,
            'user_id'   => $userId,
            'cart_id'   => $cart?->id,
            'order_id'  => $order?->id,
            'amount'    => $amount,
            'used_at'   => $order ? now() : null,
        ]);
    }

    // تُترك للاستخدام فقط إذا عندك "حجز" مسبق على الكارت
    public function convertCartRedemptionToOrder(Order $order): void
    {
        if (! $order->coupon_id) return;

        CouponRedemption::where('coupon_id', $order->coupon_id)
            ->where('cart_id', $order->cart_id)
            ->update([
                'order_id' => $order->id,
                'cart_id'  => null,
                'used_at'  => now(),
            ]);
    }

    // (اختياري) لو بدك حساب الخصم من الخدمة بدل الموديل
    public function calculate(Cart $cart, float $base, array $ctx = []): float
    {
        $c = $cart->coupon;
        if (!$c) return 0.0;
        return (float) $c->calculateDiscount($base);
    }
}
