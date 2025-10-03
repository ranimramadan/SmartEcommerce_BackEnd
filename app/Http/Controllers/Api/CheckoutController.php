<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{Cart, Order};
use App\Services\CheckoutService;

class CheckoutController extends Controller
{
    /** إنشاء طلب من السلة */
    public function placeOrder(Request $request, Cart $cart)
    {
        $billingSame = $request->boolean('billing_same_as_shipping');

        // قواعد مرنة حسب نفس-الشحن
        $rules = [
            'payment_provider_id'      => 'nullable|integer|exists:payment_providers,id',
            'billing_same_as_shipping' => 'sometimes|boolean',

            // Shipping (مطلوبة دائماً)
            'shipping.first_name' => 'required|string|max:100',
            'shipping.last_name'  => 'required|string|max:100',
            'shipping.country'    => 'required|string|max:2',
            'shipping.city'       => 'nullable|string|max:100',
            'shipping.state'      => 'nullable|string|max:100',
            'shipping.zip'        => 'nullable|string|max:20',
            'shipping.address1'   => 'required|string|max:190',
            'shipping.address2'   => 'nullable|string|max:190',
            'shipping.phone'      => 'nullable|string|max:30',
            'shipping.email'      => 'nullable|email|max:190',
        ];

        // Billing (مطلوبة فقط إذا ما كان same_as_shipping)
        if (! $billingSame) {
            $rules = array_merge($rules, [
                'billing.first_name' => 'required|string|max:100',
                'billing.last_name'  => 'required|string|max:100',
                'billing.country'    => 'required|string|max:2',
                'billing.city'       => 'nullable|string|max:100',
                'billing.state'      => 'nullable|string|max:100',
                'billing.zip'        => 'nullable|string|max:20',
                'billing.address1'   => 'required|string|max:190',
                'billing.address2'   => 'nullable|string|max:190',
                'billing.phone'      => 'nullable|string|max:30',
                'billing.email'      => 'nullable|email|max:190',
            ]);
        }

        $data = $request->validate($rules);

        /** @var CheckoutService $svc */
        $svc = app(CheckoutService::class);

        // ✅ حمّل العناصر + الكوبون لأن الخدمة تتحقق/تقفل الكوبون
        $order = $svc->placeOrderFromCart(
            $cart->load('items','coupon'),
            $data['shipping'],
            $billingSame ? null : ($data['billing'] ?? null),
            $data['payment_provider_id'] ?? null,
            $billingSame
        );

        // بدء الدفع لو Online
        $payload = $svc->startPaymentIfOnline($order);

        return response()->json([
            'order'   => $order->load('addresses','items'),
            'payment' => $payload, // null لو COD
        ], 201);
    }

    /** تحديث عناوين الطلب أثناء المعالجة */
    public function updateAddresses(Request $request, Order $order)
    {
        $data = $request->validate([
            'same_as_shipping' => 'boolean',
            'shipping'         => 'required|array',
            'billing'          => 'nullable|array',
        ]);

        $order = app(CheckoutService::class)->updateOrderAddresses(
            $order,
            $data['shipping'],
            $data['billing'] ?? null,
            (bool)($data['same_as_shipping'] ?? false)
        );

        return $order->load('addresses');
    }
}
