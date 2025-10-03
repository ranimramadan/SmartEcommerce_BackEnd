<?php

namespace App\Services;

use App\Models\{Cart, CartItem, Product, ProductVariant};
use Illuminate\Support\Facades\DB;

class CartService
{
    /** اجلب/أنشئ سلة حسب المستخدم أو الضيف */
    public function getOrCreate(?int $userId, ?string $sessionId): Cart
    {
        $q = Cart::query();

        if ($userId) {
            $q->where('user_id', $userId);
        } elseif ($sessionId) {
            $q->whereNull('user_id')->where('session_id', $sessionId);
        } else {
            // ضيف بلا session_id → أنشئ سلة بجلسة جديدة
            $sessionId = bin2hex(random_bytes(16));
            return Cart::create([
                'user_id'    => null,
                'session_id' => $sessionId,
                'status'     => 'active',
                'expires_at' => now()->addDays(config('cart.ttl_days', 7)),
            ]);
        }

        $cart = $q->first();

        if (!$cart) {
            $cart = Cart::create([
                'user_id'    => $userId,
                'session_id' => $userId ? null : $sessionId,
                'status'     => 'active',
                'expires_at' => now()->addDays(config('cart.ttl_days', 7)),
            ]);
        }

        return $cart;
    }

    /** إضافة عنصر مع قفل صفّي لعدم تكرار صفوف NULL variant */
    public function addItem(Cart $cart, int $productId, ?int $variantId, int $qty): CartItem
    {
        return DB::transaction(function () use ($cart, $productId, $variantId, $qty) {
            $qty     = max(1, (int)$qty);
            $product = Product::findOrFail($productId);

            $price   = $product->price;
            $sku     = $product->sku;
            $name    = $product->name;

            $variant = null;
            if ($variantId && class_exists(ProductVariant::class)) {
                $variant = ProductVariant::findOrFail($variantId);
                $price   = $variant->price ?? $price;
                $sku     = $variant->sku   ?? $sku;
                $name    = $product->name . ($variant->sku ? " ({$variant->sku})" : '');
            }

            $existing = CartItem::where('cart_id', $cart->id)
                ->where('product_id', $productId)
                ->when($variantId, fn($q)=>$q->where('product_variant_id', $variantId),
                                 fn($q)=>$q->whereNull('product_variant_id'))
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $existing->qty   = (int)$existing->qty + $qty;
                $existing->price = $price; // snapshot
                if (!$existing->name) $existing->name = $name;
                if (!$existing->sku)  $existing->sku  = $sku;
                $existing->save();
                $item = $existing;
            } else {
                $item = $cart->items()->create([
                    'product_id'         => $productId,
                    'product_variant_id' => $variantId,
                    'sku'                => $sku,
                    'name'               => $name,
                    'price'              => $price,
                    'qty'                => $qty,
                ]);
            }

            return $item->load('product','productVariant');
        });
    }

    /** تحديث كمية عنصر (تتوافق مع CartController::updateQty) */
    public function updateQty(CartItem $item, int $qty): CartItem
    {
        if ($qty < 1) abort(422, 'الكمية يجب أن تكون ≥ 1');

        $item->qty = $qty;
        $item->save();

        return $item->fresh(['product','productVariant']);
    }

    /** إزالة عنصر */
    public function removeItem(CartItem $item): void
    {
        $item->delete();
        $item->cart?->refresh();
    }

    /** تفريغ السلة + تصفير المجاميع */
    public function clear(Cart $cart): void
    {
        DB::transaction(function () use ($cart) {
            $cart->items()->delete();
            $cart->forceFill([
                'item_count'     => 0,
                'subtotal'       => 0,
                'discount_total' => 0,
                'shipping_total' => 0,
                'tax_total'      => 0,
                'grand_total'    => 0,
            ])->save();
        });
    }

    /** تطبيق كوبون عبر خدمة الكوبونات (إن وُجدت) */
    public function applyCoupon(Cart $cart, string $code): array
    {
        if (!class_exists(\App\Services\CouponService::class)) {
            abort(501, 'CouponService غير مفعّل.');
        }

        /** @var \App\Services\CouponService $svc */
        $svc    = app(\App\Services\CouponService::class);
        $coupon = $svc->apply($cart, $code); // يرجّع تفاصيل/ناتج التطبيق حسب خدمتك

        $cart->refresh();

        return ['cart' => $cart, 'coupon' => $coupon];
    }

    /** إزالة كوبون */
    public function removeCoupon(Cart $cart): void
    {
        if (method_exists($cart, 'coupon')) {
            $cart->coupon()->dissociate();
            $cart->save();
        }
        $cart->refresh();
    }
       // ربط/فك كوبون على السلة (بدون تسجيل Redemption)
    public function applyCouponToCart(Cart $cart, Coupon $coupon): Cart
    {
        app(\App\Services\CouponService::class)->assertValidForCart($coupon, $cart);
        $cart->coupon()->associate($coupon);
        $cart->save();
        $cart->recalculateTotals();
        return $cart;
    }

    public function removeCouponFromCart(Cart $cart): Cart
    {
        $cart->coupon()->dissociate();
        $cart->save();
        $cart->recalculateTotals();
        return $cart;
    }
}

