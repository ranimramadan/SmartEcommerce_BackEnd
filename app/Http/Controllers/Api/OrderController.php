<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\CheckoutService;

class OrderController extends Controller
{
    /**
     * GET /api/orders
     * لائحة الطلبات مع فلاتر شائعة + paginate
     *
     * فلاتر مدعومة:
     * - status: placed|accepted|processing|on_the_way|delivered|cancelled|returned
     * - payment_status: unpaid|authorized|paid|failed|refunded
     * - fulfillment_status: unfulfilled|partial|fulfilled
     * - user_id: رقم المستخدم (للوحة الإدارة)
     * - order_number: بحث دقيق برقم الطلب
     * - from/to: تاريخ إنشاء (YYYY-MM-DD)
     * - q: بحث بسيط في order_number
     * - per_page: افتراضي 15 (حد أقصى 100)
     */
    public function index(Request $request)
    {
        $q = Order::query()->with(['addresses', 'items']);

        if ($s = $request->query('status')) {
            $q->where('status', $s);
        }
        if ($ps = $request->query('payment_status')) {
            $q->where('payment_status', $ps);
        }
        if ($fs = $request->query('fulfillment_status')) {
            $q->where('fulfillment_status', $fs);
        }
        if ($uid = $request->query('user_id')) {
            $q->where('user_id', (int) $uid);
        }
        if ($num = $request->query('order_number')) {
            $q->where('order_number', $num);
        }
        if ($kw = $request->query('q')) {
            $q->where('order_number', 'like', "%{$kw}%");
        }
        if ($from = $request->query('from')) {
            $q->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $q->whereDate('created_at', '<=', $to);
        }

        // فرز بسيط (اختياري)
        $sort = $request->query('sort', '-created_at');
        if ($sort === 'created_at') {
            $q->orderBy('created_at', 'asc');
        } else {
            $q->orderBy('created_at', 'desc');
        }

        $per = min((int) $request->query('per_page', 15), 100);

        return $q->paginate($per);
    }

    /**
     * GET /api/orders/{order}
     * تفاصيل الطلب مع العلاقات الأساسية (قراءة فقط)
     */
    public function show(Order $order)
    {
        return $order->load([
            'addresses',
            'items',
            'payments',     // قراءة فقط — إدارة الدفع بمكان آخر
            'invoices',     // قراءة فقط — إنشاء/توليد PDF ضمن InvoiceController عندك
        ]);
    }

    /**
     * PUT /api/orders/{order}/status
     * تغيير حالة الطلب عبر OrderService (يسجّل في الـ timeline)
     */
    public function updateStatus(Request $request, Order $order)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([
                'placed','accepted','processing','on_the_way','delivered','cancelled','returned'
            ])],
            'note' => 'nullable|string|max:500',
        ]);

        $updated = app(OrderService::class)->transition($order, $data['status'], $data['note'] ?? null);

        return $updated->load(['addresses','items']);
    }

    /**
     * POST /api/orders/{order}/cancel
     * إلغاء الطلب (+ تحرير الحجز بالمخزون داخل OrderService إن لزم)
     */
    public function cancel(Request $request, Order $order)
    {
        $reason = $request->input('reason');
        $updated = app(OrderService::class)->transition($order, 'cancelled', $reason);

        return $updated->load(['addresses','items']);
    }

    /**
     * PUT /api/orders/{order}/addresses
     * تحديث عناوين الطلب باستخدام نفس منطق CheckoutService (idempotent)
     *
     * body:
     * {
     *   "same_as_shipping": false,
     *   "shipping": {...},
     *   "billing": {...} // اختياري
     * }
     */
    public function updateAddresses(Request $request, Order $order)
    {
        $data = $request->validate([
            'same_as_shipping' => 'boolean',
            'shipping'         => 'required|array',
            'billing'          => 'nullable|array',
        ]);

        app(CheckoutService::class)->saveOrderAddresses(
            $order,
            $data['shipping'],
            $data['billing'] ?? null,
            (bool) ($data['same_as_shipping'] ?? false)
        );

        return $order->load(['addresses']);
    }

    /**
     * GET /api/orders/{order}/timeline
     * سجل تغيّرات الحالة (order_status_events)
     */
    public function timeline(Order $order)
    {
        return $order->statusEvents()->orderBy('happened_at')->get();
    }
}
