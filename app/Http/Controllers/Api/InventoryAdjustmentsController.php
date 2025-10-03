<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use App\Models\InventoryMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryAdjustmentsController extends Controller
{
    public function index(Request $req)
    {
        $q = InventoryMovement::query()->with(['variant:id,sku']);

        if ($req->filled('sku')) {
            $sku = $req->get('sku');
            $q->whereHas('variant', fn($v)=>$v->where('sku','like',"%{$sku}%"));
        }
        if ($req->filled('reason')) {
            $q->where('reason', $req->get('reason')); // manual_adjustment, correction, damage, etc.
        }

        $q->orderByDesc('created_at');
        $per = min((int)$req->get('per_page', 20), 100);
        return $q->paginate($per);
    }

    public function store(Request $req)
    {
        $data = $req->validate([
            'product_variant_id' => 'required|integer|exists:product_variants,id',
            'qty_delta'          => 'required|integer', // موجب يزيد / سالب ينقص
            'reason'             => 'nullable|string|max:100',
            'note'               => 'nullable|string|max:500',
        ]);

        $variant = ProductVariant::lockForUpdate()->findOrFail($data['product_variant_id']);

        return DB::transaction(function () use ($variant, $data) {
            $before = (int)$variant->stock;
            $after  = $before + (int)$data['qty_delta'];

            if ($after < 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Stock cannot be negative.',
                ], 422);
            }

            $variant->stock = $after;
            $variant->save();

            $move = InventoryMovement::create([
                'product_variant_id' => $variant->id,
                'qty_change'         => (int)$data['qty_delta'],
                'stock_before'       => $before,
                'stock_after'        => $after,
                'reason'             => $data['reason'] ?? 'manual_adjustment',
                'note'               => $data['note'] ?? null,
                'user_id'            => auth()->id(),
            ]);

            return response()->json([
                'variant' => $variant->only(['id','sku','stock']),
                'movement'=> $move,
            ], 201);
        });
    }
}
