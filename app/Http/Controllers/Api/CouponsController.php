<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CouponsController extends Controller
{
    /** GET /api/coupons */
    public function index(Request $req)
    {
        $q = Coupon::query();

        // بحث نصّي بسيط بالكود/الوصف
        if ($s = $req->query('q')) {
            $q->where(function($w) use ($s){
                $w->where('code','like',"%{$s}%")
                  ->orWhere('description','like',"%{$s}%");
            });
        }

        // فلتر بالكود الدقيق (يدعم case-insensitive عبر UPPER)
        if ($code = $req->query('code')) {
            $q->where('code', strtoupper(trim($code)));
        }

        // is_active=1/0
        if (!is_null($req->query('is_active'))) {
            $q->where('is_active', (bool)$req->boolean('is_active'));
        }

        // current=1 → ضمن نافذة الزمن + مفعّل
        if ($req->boolean('current')) {
            $q->activeNow();
        }

        // type = percent|amount|free_shipping
        if ($t = $req->query('type')) {
            $q->where('type', $t);
        }

        $q->orderByDesc('created_at');

        $per = min((int)$req->query('per_page', 20), 100);
        return $q->paginate($per);
    }

    /** GET /api/coupons/{coupon} */
    public function show(Coupon $coupon)
    {
        return response()->json($coupon);
    }

    /** POST /api/coupons */
    public function store(Request $req)
    {
        $data = $req->validate([
            'code'   => 'required|string|max:64|unique:coupons,code',
            'type'   => ['required', Rule::in(['percent','amount','free_shipping'])],
            'value'  => 'nullable|numeric|min:0',

            // حدود دنيا
            'min_cart_total'   => 'nullable|numeric|min:0',
            'min_items_count'  => 'nullable|integer|min:0',

            // نافذة زمنية
            'start_at' => 'nullable|date',
            'end_at'   => 'nullable|date|after_or_equal:start_at',

            // دعم أسماء قديمة (اختياري): starts_at/ends_at → نحولها لـ start_at/end_at
            'starts_at' => 'nullable|date',
            'ends_at'   => 'nullable|date|after_or_equal:starts_at',

            'is_active' => 'boolean',

            // حدود استخدام
            'max_uses'           => 'nullable|integer|min:0',
            'max_uses_per_user'  => 'nullable|integer|min:0',

            // حقل وصفي (اختياري)
            'description' => 'nullable|string',
        ]);

        // تحويل legacy inputs
        if (!isset($data['start_at']) && isset($data['starts_at'])) {
            $data['start_at'] = $data['starts_at'];
        }
        if (!isset($data['end_at']) && isset($data['ends_at'])) {
            $data['end_at'] = $data['ends_at'];
        }

        // دعم legacy: لو وصل min_subtotal نعتبرها min_cart_total
        if (!isset($data['min_cart_total']) && $req->filled('min_subtotal')) {
            $data['min_cart_total'] = (float) $req->input('min_subtotal');
        }

        // UPPER للكود
        $data['code'] = strtoupper(trim($data['code']));
        $data['is_active'] = $data['is_active'] ?? true;

        $coupon = Coupon::create($data);

        return response()->json($coupon, 201);
    }

    /** PUT/PATCH /api/coupons/{coupon} */
    public function update(Request $req, Coupon $coupon)
    {
        $data = $req->validate([
            'code'  => ['sometimes','string','max:64', Rule::unique('coupons','code')->ignore($coupon->id)],
            'type'  => ['sometimes', Rule::in(['percent','amount','free_shipping'])],
            'value' => 'nullable|numeric|min:0',

            'min_cart_total'  => 'nullable|numeric|min:0',
            'min_items_count' => 'nullable|integer|min:0',

            'start_at' => 'nullable|date',
            'end_at'   => 'nullable|date|after_or_equal:start_at',

            // دعم legacy اختياري
            'starts_at' => 'nullable|date',
            'ends_at'   => 'nullable|date|after_or_equal:starts_at',

            'is_active' => 'boolean',

            'max_uses'           => 'nullable|integer|min:0',
            'max_uses_per_user'  => 'nullable|integer|min:0',

            'description' => 'nullable|string',
        ]);

        // تحويل legacy inputs
        if (!isset($data['start_at']) && $req->filled('starts_at')) {
            $data['start_at'] = $req->input('starts_at');
        }
        if (!isset($data['end_at']) && $req->filled('ends_at')) {
            $data['end_at'] = $req->input('ends_at');
        }
        if (isset($data['code'])) {
            $data['code'] = strtoupper(trim($data['code']));
        }

        // دعم legacy: min_subtotal → min_cart_total
        if (!isset($data['min_cart_total']) && $req->filled('min_subtotal')) {
            $data['min_cart_total'] = (float) $req->input('min_subtotal');
        }

        $coupon->update($data);
        return response()->json($coupon);
    }

    /** DELETE /api/coupons/{coupon} */
    public function destroy(Coupon $coupon)
    {
        $coupon->delete();
        return response()->json(null, 204);
    }

    /** POST /api/coupons/{coupon}/activate */
    public function activate(Coupon $coupon)
    {
        $coupon->update(['is_active' => true]);
        return response()->json($coupon);
    }

    /** POST /api/coupons/{coupon}/deactivate */
    public function deactivate(Coupon $coupon)
    {
        $coupon->update(['is_active' => false]);
        return response()->json($coupon);
    }

    /** (اختياري إداري) POST /api/coupons/preview  — تجربة الكوبون على سلة بدون تطبيقه */
    public function preview(Request $request)
    {
        $data = $request->validate([
            'cart_id' => 'required|integer|exists:carts,id',
            'code'    => 'required|string|max:64',
        ]);

        $cart   = \App\Models\Cart::with('items')->findOrFail($data['cart_id']);
        $coupon = Coupon::where('code', strtoupper(trim($data['code'])))->firstOrFail();

        if (!$coupon->isValidForCart($cart)) {
            return response()->json(['valid' => false, 'discount' => 0], 422);
        }

        $base = (float) $cart->subtotal; // أساس الخصم: subtotal بعد خصومات السطور
        $discount = (float) $coupon->calculateDiscount($base);

        return response()->json([
            'valid'    => true,
            'base'     => $base,
            'discount' => $discount,
            'type'     => $coupon->type,
        ]);
    }
}
