<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\{Order, Payment, PaymentProvider};
use App\Services\Payment\PaymentService;
use App\Services\RefundService;

class PaymentController extends Controller
{
    /** قائمة المدفوعات (لشاشة إدارة المدفوعات) */
    public function index(Request $req)
    {
        $q = Payment::query()
            ->with([
                'order:id,order_number,user_id,payment_status,grand_total,currency',
                'provider:id,code,name,type',
            ]);

        if ($req->filled('status'))         $q->where('status', $req->get('status'));          // authorized|captured|refunded|failed
        if ($req->filled('provider_id'))    $q->where('payment_provider_id', $req->integer('provider_id'));
        if ($req->filled('provider_code'))  $q->whereHas('provider', fn($w)=>$w->where('code',$req->get('provider_code')));
        if ($req->filled('order_id'))       $q->where('order_id', $req->integer('order_id'));
        if ($req->filled('transaction_id')) $q->where('transaction_id', $req->get('transaction_id'));
        if ($req->filled('from'))           $q->whereDate('created_at', '>=', $req->date('from'));
        if ($req->filled('to'))             $q->whereDate('created_at', '<=', $req->date('to'));

        $q->orderByDesc('created_at');
        $per = min((int)$req->get('per_page', 20), 100);

        // include=refunds لتحميل الاسترجاعات
        if ($req->boolean('include_refunds')) {
            $q->with('refunds:id,payment_id,amount,status,created_at');
        }

        return $q->paginate($per);
    }

    /** تفاصيل دفع معيّن */
    public function show(Payment $payment)
    {
        return response()->json(
            $payment->load(
                'order:id,order_number,user_id,payment_status,grand_total,currency',
                'provider:id,code,name,type',
                'refunds:id,payment_id,amount,status,reason,provider_refund_id,created_at'
            )
        );
    }

    /** مزودات الدفع الفعّالة (لواجهة الكاشير) */
    public function providers()
    {
        return PaymentProvider::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id','code','name','type','is_active']);
    }

    /** بدء الدفع لأمر معيّن عبر كود مزوّد (stripe|cod|...) */
    public function start(Request $request, Order $order)
    {
        $data = $request->validate([
            'provider' => ['required','string', Rule::in(
                PaymentProvider::pluck('code')->all() // أو ثبّتيها يدوياً
            )],
        ]);

        $payload = app(PaymentService::class)->startPayment($order, $data['provider']);
        return response()->json(['payment' => $payload]);
    }

    /** تأكيد COD (التقاط آخر authorized فقط) */
    public function confirmCod(Order $order)
    {
        $payload = app(PaymentService::class)->confirm($order, 'cod');
        return response()->json(['payment' => $payload]);
    }

    /** إنشاء استرجاع لدفعة */
    public function refund(Request $request, Payment $payment)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'nullable|string|max:255'
        ]);

        $ok = app(RefundService::class)->refund($payment, (float) $data['amount'], $data['reason'] ?? null);

        return $ok
            ? response()->json(['message' => 'Refunded'])
            : response()->json(['message' => 'Refund failed'], 422);
    }
}
