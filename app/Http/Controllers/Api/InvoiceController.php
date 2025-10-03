<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\{Order, Invoice};
use App\Services\InvoiceService;

class InvoicesController extends Controller
{
    /* ===================== Admin/List ===================== */

    /** GET /api/invoices  (قائمة عامة مع فلاتر) */
    public function index(Request $req)
    {
        $q = Invoice::query()->with('order:id,order_number,user_id');

        if ($req->filled('status')) {
            $req->validate([
                'status' => [Rule::in(['draft','issued','paid','void','refunded'])],
            ]);
            $q->where('status', $req->get('status'));
        }

        if ($req->filled('order_id')) {
            $q->where('order_id', (int) $req->get('order_id'));
        }

        if ($s = $req->get('q')) {
            $q->where(function ($w) use ($s) {
                $w->where('invoice_no', 'like', "%{$s}%")
                  ->orWhereHas('order', fn($oq) => $oq->where('order_number', 'like', "%{$s}%"));
            });
        }

        $q->orderByDesc('created_at');
        $per = min((int) $req->get('per_page', 20), 100);

        return $q->paginate($per);
    }

    /** GET /api/orders/{order}/invoices  (فواتير طلب معيّن) */
    public function byOrder(Order $order)
    {
        return $order->invoices()
            ->with('items')
            ->orderBy('id', 'desc')
            ->get();
    }

    /** GET /api/invoices/{invoice} */
    public function show(Invoice $invoice)
    {
        return $invoice->load('items', 'order');
    }

    /* ===================== Create / Update ===================== */

    /**
     * POST /api/orders/{order}/invoices
     * إنشاء فاتورة من طلب (كاملة أو جزئية حسب lines)
     *
     * body:
     * {
     *   "lines": [{"order_item_id":1,"qty":2,"discount":0,"tax":0}], // اختياري
     *   "status": "issued|draft|paid|void|refunded",
     *   "notes": "...",
     *   "shipping_total": 0,
     *   "due_at": "YYYY-MM-DD"
     * }
     */
    public function createFromOrder(Request $req, Order $order, InvoiceService $svc)
    {
        $data = $req->validate([
            'lines'                   => 'nullable|array',
            'lines.*.order_item_id'   => 'required_with:lines|integer|exists:order_items,id',
            'lines.*.qty'             => 'required_with:lines|integer|min:1',
            'lines.*.discount'        => 'nullable|numeric|min:0',
            'lines.*.tax'             => 'nullable|numeric|min:0',
            'status'                  => ['nullable', Rule::in(['issued','draft','paid','void','refunded'])],
            'notes'                   => 'nullable|string|max:500',
            'shipping_total'          => 'nullable|numeric|min:0',
            'due_at'                  => 'nullable|date',
        ]);

        $inv = $svc->createFromOrder(
            $order,
            $data['lines'] ?? null,
            $data['status'] ?? \App\Models\Invoice::STATUS_ISSUED,
            $data['notes'] ?? null,
            (float) ($data['shipping_total'] ?? 0),
            $data['due_at'] ?? null
        );

        if (($data['status'] ?? null) === \App\Models\Invoice::STATUS_PAID) {
            app(InvoiceService::class)->markAsPaid($inv);
        }

        return response()->json($inv->load('items'), 201);
    }

    /**
     * POST /api/orders/{order}/invoices/issue
     * إصدار سريع: يفوتر تلقائياً كل البنود المتبقية للطلب.
     */
    public function issue(Request $req, Order $order, InvoiceService $svc)
    {
        $data = $req->validate([
            'status'         => ['nullable', Rule::in(['issued','paid','draft','void','refunded'])],
            'notes'          => 'nullable|string|max:500',
            'shipping_total' => 'nullable|numeric|min:0',
            'due_at'         => 'nullable|date',
        ]);

        $inv = $svc->createFromOrder(
            $order,
            null, // كل المتبقي تلقائياً
            $data['status'] ?? \App\Models\Invoice::STATUS_ISSUED,
            $data['notes'] ?? null,
            (float) ($data['shipping_total'] ?? 0),
            $data['due_at'] ?? null
        );

        if (($data['status'] ?? null) === \App\Models\Invoice::STATUS_PAID) {
            app(InvoiceService::class)->markAsPaid($inv);
        }

        return $inv->load('items');
    }

    /**
     * POST /api/invoices/{invoice}/items
     * إضافة بنود جزئية لاحقاً.
     */
    public function addItems(Request $req, Invoice $invoice, InvoiceService $svc)
    {
        $data = $req->validate([
            'lines'                   => 'required|array|min:1',
            'lines.*.order_item_id'   => 'required|integer|exists:order_items,id',
            'lines.*.qty'             => 'required|integer|min:1',
            'lines.*.discount'        => 'nullable|numeric|min:0',
            'lines.*.tax'             => 'nullable|numeric|min:0',
        ]);

        $svc->addItems($invoice, $data['lines']);

        return response()->json($invoice->fresh('items'));
    }

    /** POST /api/invoices/{invoice}/mark-paid */
    public function markPaid(Invoice $invoice)
    {
        app(InvoiceService::class)->markAsPaid($invoice);
        return $invoice->refresh();
    }

    /* ===================== Helpers ===================== */

    /**
     * GET /api/orders/{order}/invoiceable
     * مساعد للواجهة: الكميات المتبقية القابلة للفوترة لكل بند.
     */
    public function invoiceable(Order $order, InvoiceService $svc)
    {
        $order->load('items:id,order_id,qty,price,name');
        $rows = [];
        foreach ($order->items as $oi) {
            $rows[] = [
                'order_item_id' => $oi->id,
                'name'          => $oi->name,
                'ordered_qty'   => (int) $oi->qty,
                'remaining_qty' => $svc->remainingInvoiceableQty($oi),
                'unit_price'    => (float) $oi->price,
            ];
        }
        return response()->json($rows);
    }

    /* ===================== PDF ===================== */

    /** POST /api/invoices/{invoice}/pdf  (generate or regenerate) */
    public function generatePdf(Request $req, Invoice $invoice, InvoiceService $svc)
    {
        $data = $req->validate([
            'locale'            => 'nullable|string|in:ar,en',
            'force'             => 'boolean',
            'respect_existing'  => 'boolean',
        ]);

        $path = $svc->generatePdf(
            $invoice,
            $data['locale'] ?? null,
            (bool) ($data['force'] ?? false),
            (bool) ($data['respect_existing'] ?? true)
        );

        return response()->json([
            'pdf_path'   => $path,
            'public_url' => $svc->publicUrl($invoice->fresh()),
        ]);
    }

    /** GET /api/invoices/{invoice}/public-url */
    public function publicUrl(Invoice $invoice, InvoiceService $svc)
    {
        return response()->json(['public_url' => $svc->publicUrl($invoice)]);
    }
}
