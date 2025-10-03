<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use App\Models\{Cart, CartItem, Product};
use App\Services\CartService;

class CartController extends Controller
{
    /** عرض/إنشاء السلة الحالية (مستخدم أو ضيف)، مع دمج سلة الضيف بعد تسجيل الدخول */
    public function show(Request $request)
    {
        [$userId, $sessionId] = $this->resolveContext($request);

        /** @var CartService $svc */
        $svc  = app(CartService::class);
        $cart = $svc->getOrCreate($userId, $sessionId);

        // دمج سلة الضيف إذا وُجدت بعد تسجيل الدخول
        if ($userId && $sessionId) {
            $this->mergeGuestCart($svc, $userId, $sessionId, $cart);
        }

        $cart->load(['items.product','items.productVariant']);

        $resp = $this->respondWithCart($cart);

        // ضيف: نعيد X-Session-Id إذا ما كان مبعوث
        if (!$userId && $cart->session_id && !$sessionId) {
            $resp->headers->set('X-Session-Id', $cart->session_id);
        }

        return $resp;
    }

    /** إضافة عنصر للسلة */
    public function addItem(Request $request)
    {
        $data = $request->validate([
            'product_id'         => ['required','integer','exists:products,id'],
            'product_variant_id' => ['nullable','integer'],
            'qty'                => ['required','integer','min:1'],
        ]);

        $product   = Product::findOrFail($data['product_id']);
        $variantId = $data['product_variant_id'] ?? null;

        // (تحقق اختياري) الـ Variant يتبع نفس المنتج
        if ($variantId) {
            $request->validate([
                'product_variant_id' => [
                    Rule::exists('product_variants', 'id')->where('product_id', $product->id),
                ],
            ]);
        }

        [$userId, $sessionId] = $this->resolveContext($request);

        /** @var CartService $svc */
        $svc  = app(CartService::class);
        $cart = $svc->getOrCreate($userId, $sessionId);

        $item = $svc->addItem($cart, $product->id, $variantId, (int)$data['qty']);

        $cart = $cart->fresh()->load(['items.product','items.productVariant']);
        $resp = $this->respondWithCart($cart, 201, [
            'message' => 'added',
            'item'    => $item->load('product','productVariant'),
        ]);

        // ضيف: نعيد X-Session-Id إذا ما كان مبعوث
        if (!$userId && $cart->session_id && !$sessionId) {
            $resp->headers->set('X-Session-Id', $cart->session_id);
        }

        return $resp;
    }

    /** تحديث كمية عنصر (اسم الميثود حسب اللي كان عندِك) */
    public function updateQty(Request $request, int $id)
    {
        $data = $request->validate([
            'qty' => ['required','integer','min:1'],
        ]);

        /** @var CartService $svc */
        $svc  = app(CartService::class);
        $item = CartItem::with('cart')->findOrFail($id);

        $item = $svc->updateQty($item, (int)$data['qty']);

        $cart = $item->cart->fresh()->load(['items.product','items.productVariant']);

        return $this->respondWithCart($cart, 200, [
            'message' => 'updated',
            'item'    => $item->load('product','productVariant'),
        ]);
    }

    /** إزالة عنصر */
    public function removeItem(int $id)
    {
        /** @var CartService $svc */
        $svc  = app(CartService::class);
        $item = CartItem::with('cart')->findOrFail($id);

        $svc->removeItem($item);

        return response()->json(['message' => 'removed']);
    }

    /** تفريغ السلة */
    public function clear(Request $request)
    {
        [$userId, $sessionId] = $this->resolveContext($request);

        /** @var CartService $svc */
        $svc  = app(CartService::class);
        $cart = $svc->getOrCreate($userId, $sessionId);

        $svc->clear($cart);

        return response()->json(['message' => 'cleared']);
    }

    /** تطبيق كوبون على السلة (بدون تسجيل Redemption) */
    public function applyCoupon(Request $request)
    {
        $data = $request->validate(['code' => 'required|string|max:64']);

        [$userId, $sessionId] = $this->resolveContext($request);

        /** @var CartService $svc */
        $svc  = app(\App\Services\CartService::class);
        $cart = $svc->getOrCreate($userId, $sessionId)->loadMissing('items');

        $coupon = \App\Models\Coupon::where('code', strtoupper(trim($data['code'])))->firstOrFail();

        // تحقق فقط – ما نسجّل Redemption هون
        app(\App\Services\CouponService::class)->assertValidForCart($coupon, $cart);

        // لو التحقق نجح: علّقي الكوبون على السلة ثم أعيدي الحساب
        $cart->coupon()->associate($coupon);
        $cart->save();
        $cart->recalculateTotals();

        $cart = $cart->fresh()->load(['items.product','items.productVariant']);
        return $this->respondWithCart($cart, 200, ['message' => 'coupon_applied']);
    }

    /** إزالة كوبون من السلة (بدون تسجيل Redemption) */
    public function removeCoupon(Request $request)
    {
        [$userId, $sessionId] = $this->resolveContext($request);
        $cart = app(\App\Services\CartService::class)->getOrCreate($userId, $sessionId);

        if ($cart->coupon) {
            $cart->coupon()->dissociate();
            $cart->save();
            $cart->recalculateTotals();
        }

        $cart = $cart->fresh()->load(['items.product','items.productVariant']);
        return $this->respondWithCart($cart, 200, ['message' => 'coupon_removed']);
    }

    /* ===================== Helpers ===================== */

    private function resolveContext(Request $request): array
    {
        $userId    = optional($request->user())->id;
        $sessionId = (string)($request->header('X-Session-Id') ?: $request->cookie('X-Session-Id') ?: $request->input('session_id',''));
        return [$userId ?: null, $sessionId ?: null];
    }

    private function mergeGuestCart(CartService $svc, int $userId, string $sessionId, Cart $userCart): void
    {
        DB::transaction(function () use ($svc, $userId, $sessionId, $userCart) {
            $guest = Cart::query()
                ->whereNull('user_id')
                ->where('session_id', $sessionId)
                ->where('status', 'active')
                ->first();

            if (!$guest || $guest->id === $userCart->id) return;

            foreach ($guest->items as $gi) {
                $svc->addItem($userCart, $gi->product_id, $gi->product_variant_id, (int)$gi->qty);
            }

            $guest->items()->delete();
            $guest->delete();

            $userCart->refresh();
        });
    }

    private function respondWithCart(Cart $cart, int $status = 200, array $extra = [])
    {
        $payload = array_merge($extra, [
            'cart'   => $cart,
            'totals' => [
                'subtotal' => (float) $cart->subtotal,
                'discount' => (float) $cart->discount_total,
                'shipping' => (float) $cart->shipping_total,
                'tax'      => (float) $cart->tax_total,
                'total'    => (float) $cart->grand_total,
            ],
        ]);

        return response()->json($payload, $status);
    }
}
