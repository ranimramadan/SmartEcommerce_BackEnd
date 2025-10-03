<?php

namespace App\Services;

use App\Models\{Cart, Order, OrderItem, OrderAddress, PaymentProvider, Coupon};
use Illuminate\Support\Facades\DB;

class CheckoutService
{
    /**
     * إنشاء طلب من سلة + عناوين + (اختياري) حجز مخزون.
     * - يتحقق من الكوبون ويطبّق أثره داخل نفس الـTransaction.
     * - يسجّل الاستهلاك (Redemption) فقط بعد إنشاء الطلب بنجاح.
     */
    public function placeOrderFromCart(
        Cart $cart,
        array $shipping,
        ?array $billing = null,
        ?int $paymentProviderId = null,
        bool $billingSameAsShipping = false
    ): Order {
        $cart->loadMissing(['items','coupon']);
        if ($cart->items->isEmpty()) {
            throw new \RuntimeException('Cart is empty.');
        }

        return DB::transaction(function () use ($cart, $shipping, $billing, $paymentProviderId, $billingSameAsShipping) {

            /** ----------------------------------------------------------------
             *  (A) Snapshot دقيق من عناصر السلة لمنع double-count للخصم
             * ----------------------------------------------------------------*/
            $sub = 0.0;
            $itemDisc = 0.0;
            foreach ($cart->items as $ci) {
                $sub      += (float) $ci->line_subtotal;
                $itemDisc += (float) $ci->line_discount;
            }

            /** ----------------------------------------------------------------
             *  (B) قفل + تحقق نهائي للكوبون (إن وجد)
             * ----------------------------------------------------------------*/
            $lockedCoupon = null;
            if ($cart->coupon_id) {
                $lockedCoupon = Coupon::whereKey($cart->coupon_id)->lockForUpdate()->first();
                app(\App\Services\CouponService::class)->assertValidForCart($lockedCoupon, $cart);
            }

            /** ----------------------------------------------------------------
             *  (C) إنشاء الطلب من السلة (Snapshot)
             *      - subtotal من مجموع line_subtotal
             *      - discount_total = خصم السطور فقط (بدون خصم الكوبون)
             *      - grand_total = 0 (سيُعاد حسابه لاحقًا)
             * ----------------------------------------------------------------*/
            $order = Order::create([
                'order_number'        => Order::generateOrderNumber(),
                'user_id'             => $cart->user_id,
                'cart_id'             => $cart->id,
                'coupon_id'           => $cart->coupon_id,
                'status'              => 'placed',
                'payment_status'      => 'unpaid',
                'fulfillment_status'  => 'unfulfilled',
                'subtotal'            => $sub,
                'discount_total'      => $itemDisc, // خصم السطور فقط
                'shipping_total'      => $cart->shipping_total,
                'tax_total'           => $cart->tax_total,
                'grand_total'         => 0, // يُعاد حسابه لاحقًا
                'currency'            => $cart->currency,
                'payment_provider_id' => $paymentProviderId,
            ]);

            /** ----------------------------------------------------------------
             *  (D) عناصر الطلب (Snapshot من عناصر السلة)
             * ----------------------------------------------------------------*/
            foreach ($cart->items as $ci) {
                OrderItem::create([
                    'order_id'           => $order->id,
                    'product_id'         => $ci->product_id,
                    'product_variant_id' => $ci->product_variant_id,
                    'sku'                => $ci->sku,
                    'name'               => $ci->name,
                    'price'              => $ci->price,
                    'qty'                => $ci->qty,
                    'line_subtotal'      => $ci->line_subtotal,
                    'line_discount'      => $ci->line_discount,
                    'line_total'         => $ci->line_total,
                    'options'            => $ci->options,
                ]);
            }

            /** ----------------------------------------------------------------
             *  (E) عناوين الطلب (Idempotent)
             * ----------------------------------------------------------------*/
            $this->saveOrderAddresses($order, $shipping, $billing, $billingSameAsShipping);

            /** ----------------------------------------------------------------
             *  (F) تطبيق أثر الكوبون على لقطة الطلب (إن وجد)
             *      - يزيد خصم الكوبون داخل discount_total
             *      - يصفّر الشحن لو النوع free_shipping
             *      - يخزّن لقطة coupon_* على الطلب
             * ----------------------------------------------------------------*/
            if ($lockedCoupon) {
                app(\App\Services\CouponService::class)->applyOnOrderSnapshot($lockedCoupon, $order);
            }

            /** ----------------------------------------------------------------
             *  (G) إعادة حساب المجاميع للطلب
             *      - الآن أصبح discount_total = خصم السطور + خصم الكوبون
             * ----------------------------------------------------------------*/
            if (method_exists($order, 'recalculateTotals')) {
                $order->recalculateTotals();
            } else {
                $row = $order->items()
                    ->selectRaw('SUM(line_subtotal) as sub, SUM(line_discount) as disc')
                    ->first();

                $subtotal = (float) ($row->sub ?? 0);
                // بعد applyOnOrderSnapshot: discount_total يحوي كل الخصومات
                $allDisc  = (float) $order->discount_total;

                $grand    = max(
                    0.0,
                    $subtotal - $allDisc + (float)$order->shipping_total + (float)$order->tax_total
                );

                $order->fill([
                    'subtotal'       => $subtotal,
                    'discount_total' => $allDisc,
                    'grand_total'    => $grand,
                ])->save();
            }

            /** ----------------------------------------------------------------
             *  (H) حجز المخزون (إن وُجدت الخدمة)
             * ----------------------------------------------------------------*/
            if (class_exists(\App\Services\InventoryService::class)) {
                app(\App\Services\InventoryService::class)->reserveOrder($order);
            }

            /** ----------------------------------------------------------------
             *  (I) تسجيل الاستهلاك النهائي للكوبون — قيمة خصم الكوبون فقط
             * ----------------------------------------------------------------*/
            if ($lockedCoupon) {
                app(\App\Services\CouponService::class)->recordRedemption(
                    $lockedCoupon,
                    $cart->user_id,
                    $cart,
                    $order,
                    (float) ($order->coupon_discount ?? 0) // فقط خصم الكوبون
                );
            }

            /** ----------------------------------------------------------------
             *  (J) تحويل السلة + تتبّع الحالة
             * ----------------------------------------------------------------*/
            if (method_exists($cart, 'markAsConverted')) {
                $cart->markAsConverted();
            } else {
                $cart->update(['status' => 'converted']);
            }

            if (class_exists(\App\Services\OrderService::class)) {
                app(\App\Services\OrderService::class)->recordStatus($order, 'placed', 'Order placed');
            }

            return $order->refresh()->load(['items','shippingAddress','billingAddress']);
        });
    }

    /**
     * بدء الدفع (إن كان Online) — تُستدعى بعد إنشاء الطلب.
     */
    public function startPaymentIfOnline(Order $order): ?array
    {
        if (!$order->payment_provider_id) return null;

        $provider = PaymentProvider::find($order->payment_provider_id);
        if (!$provider || $provider->type !== 'online') return null;

        return app(\App\Services\Payment\PaymentService::class)
            ->startPayment($order, $provider->code);
    }

    /**
     * حفظ/تحديث عناوين الشحن/الفوترة للطلب (Idempotent).
     */
    public function saveOrderAddresses(
        Order $order,
        array $shipping,
        ?array $billing = null,
        bool $sameAsShipping = false
    ): void {
        $base = [
            'first_name'=>null,'last_name'=>null,'company'=>null,
            'country'=>null,'state'=>null,'city'=>null,'zip'=>null,
            'address1'=>null,'address2'=>null,'phone'=>null,'email'=>null,
        ];

        $shipping = array_merge($base, $shipping ?? []);
        \App\Models\OrderAddress::updateOrCreate(
            ['order_id' => $order->id, 'type' => 'shipping'],
            $shipping + ['order_id' => $order->id, 'type' => 'shipping']
        );

        if ($sameAsShipping || empty($billing)) {
            $billing = $shipping;
        } else {
            $billing = array_merge($base, $billing ?? []);
        }

        \App\Models\OrderAddress::updateOrCreate(
            ['order_id' => $order->id, 'type' => 'billing'],
            $billing + ['order_id' => $order->id, 'type' => 'billing']
        );
    }

    /** تسهيلات واجهة إن احتجتيها لاحقًا */
    public function updateOrderAddresses(Order $order, array $shipping, ?array $billing, bool $sameAsShipping = false): Order
    {
        $this->saveOrderAddresses($order, $shipping, $billing, $sameAsShipping);
        return $order->refresh()->load(['shippingAddress','billingAddress']);
    }
}
