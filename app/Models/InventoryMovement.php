<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use App\Models\InventoryMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InventoryAdjustmentsController extends Controller
{
    /** لائحة الحركات مع فلاتر بسيطة */
    public function index(Request $req)
    {
        $q = InventoryMovement::query()->with([
            'product:id,sku',
            'productVariant:id,sku',
            'user:id,name',
        ]);

        // فلترة بالـSKU (من المنتج أو المتغير)
        if ($sku = $req->query('sku')) {
            $q->where(function ($qq) use ($sku) {
                $qq->whereHas('productVariant', fn($v) => $v->where('sku','like',"%{$sku}%"))
                   ->orWhereHas('product', fn($p) => $p->where('sku','like',"%{$sku}%"));
            });
        }

        // فلترة بحسب IDs
        if ($rid = $req->query('product_id')) {
            $q->where('product_id', (int) $rid);
        }
        if ($vid = $req->query('product_variant_id')) {
            $q->where('product_variant_id', (int) $vid);
        }

        // فلترة بحسب السبب (ضمن enum المعتمد)
        if ($req->filled('reason')) {
            $req->validate([
                'reason' => [Rule::in([
                    InventoryMovement::REASON_ORDER_RESERVED,
                    InventoryMovement::REASON_ORDER_CANCELLED,
                    InventoryMovement::REASON_ORDER_SHIPPED,
                    InventoryMovement::REASON_MANUAL_ADJUSTMENT,
                ])],
            ]);
            $q->where('reason', $req->query('reason'));
        }

        $q->orderByDesc('created_at');
        $per = min((int) $req->query('per_page', 20), 100);
        return $q->paginate($per);
    }

    /** تعديل يدوي للمخزون (يزيد/ينقص) مع حفظ before/after + ملاحظة */
    public function store(Request $req)
    {
        $data = $req->validate([
            'product_variant_id' => 'required|integer|exists:product_variants,id',
            'qty_delta'          => 'required|integer', // موجب يزيد / سالب ينقص
            'reason'             => ['nullable', Rule::in([
                InventoryMovement::REASON_MANUAL_ADJUSTMENT,
                // يمكن السماح بأسباب إضافية لاحقًا إن رغبتِ
            ])],
            'note'               => 'nullable|string|max:500',
        ]);

        // قفل صف سلالة المنتج/المتغير لمنع التنافس على المخزون
        $variant = ProductVariant::lockForUpdate()->findOrFail($data['product_variant_id']);

        return DB::transaction(function () use ($variant, $data, $req) {
            $delta    = (int) $data['qty_delta'];
            $before   = (int) $variant->stock;
            $after    = $before + $delta;

            if ($after < 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Stock cannot be negative.',
                ], 422);
            }
            if ($delta === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'qty_delta cannot be zero.',
                ], 422);
            }

            // 1) عدّلي الرصيد
            $variant->stock = $after;
            $variant->save();

            // 2) سجّلي الحركة — نستخدم recordMovement مع الحقول الإضافية
            $movement = InventoryMovement::recordMovement(
                productId: $variant->product_id,                        // مطلوب حسب السكيمة
                change:    $delta,                                      // موجب/سالب
                reason:    $data['reason'] ?? InventoryMovement::REASON_MANUAL_ADJUSTMENT,
                variantId: $variant->id,
                reference: null,                                        // لا مرجع إداري معيّن
                userId:    auth()->id(),
                extra:     [
                    'stock_before' => $before,
                    'stock_after'  => $after,
                    'note'         => $req->input('note'),
                ]
            );

            return response()->json([
                'variant'  => $variant->only(['id','sku','stock']),
                'movement' => $movement,
            ], 201);
        });
    }
}
