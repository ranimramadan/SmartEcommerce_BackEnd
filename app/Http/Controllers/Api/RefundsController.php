<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Refund;

class RefundsController extends Controller
{
    public function index(Request $req)
    {
        $q = Refund::query()->with([
            'payment:id,order_id,amount,currency,status,transaction_id,payment_provider_id',
            'order:id,order_number,user_id,grand_total,currency',
        ]);

        if ($req->filled('status'))   $q->where('status', $req->get('status'));         // pending|succeeded|failed
        if ($req->filled('order_id')) $q->where('order_id', $req->integer('order_id'));
        if ($req->filled('payment_id')) $q->where('payment_id', $req->integer('payment_id'));
        if ($req->filled('from'))     $q->whereDate('created_at', '>=', $req->date('from'));
        if ($req->filled('to'))       $q->whereDate('created_at', '<=', $req->date('to'));

        $q->orderByDesc('created_at');
        $per = min((int)$req->get('per_page', 20), 100);

        return $q->paginate($per);
    }

    public function show(Refund $refund)
    {
        return response()->json($refund->load('payment', 'order'));
    }
}
