<?php

namespace App\Services;

use App\Models\Order;
use App\Models\InventoryMovement;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * احجز/اخصم مخزون الطلب (خيار A)
     * - يسجّل حركات order_reserved سالبة لكل بند.
     * - Idempotent: ما بحجز مرتين لنفس البنود.
     */
    public function reserveOrder(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $order->loadMissing('items:id,order_id,product_id,product_variant_id,qty');

            foreach ($order->items as $item) {
                $productId = $item->product_id;
                $variantId = $item->product_variant_id;

                // كمية محجوزة سابقًا لهالبند (لنفس الطلب + المنتج + المتغير)
                $alreadyReserved = InventoryMovement::query()
                    ->where('reference_type', 'order')
                    ->where('reference_id',  $order->id)
                    ->where('product_id',    $productId)
                    ->where(function ($q) use ($variantId) {
                        $variantId
                            ? $q->where('product_variant_id', $variantId)
                            : $q->whereNull('product_variant_id');
                    })
                    ->where('reason', InventoryMovement::REASON_ORDER_RESERVED)
                    ->sum('change'); // سالب

                $alreadyReservedAbs = abs((int) $alreadyReserved);
                $need = (int) $item->qty - $alreadyReservedAbs;

                if ($need > 0) {
                    InventoryMovement::recordMovement(
                        productId: $productId,
                        change:    -$need, // سالب (حجز)
                        reason:    InventoryMovement::REASON_ORDER_RESERVED,
                        variantId: $variantId,
                        reference: $order
                    );
                }
            }
        });
    }

    /**
     * أرجِع المخزون عند إلغاء الطلب (خيار A)
     * - يعكس الحجز فقط (ما عنا حركة للشحن بخيار A).
     * - Idempotent: يرجّع المتبقّي فقط لو انطلب مرتين.
     */
    public function releaseOrder(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $order->loadMissing('items:id,order_id,product_id,product_variant_id,qty');

            foreach ($order->items as $item) {
                $productId = $item->product_id;
                $variantId = $item->product_variant_id;

                // إجمالي ما حُجز سابقًا (سالب) لهالمنتج/المتغير ضمن هالطلب
                $reserved = (int) abs(InventoryMovement::query()
                    ->where('reference_type', 'order')
                    ->where('reference_id',  $order->id)
                    ->where('product_id',    $productId)
                    ->where(function ($q) use ($variantId) {
                        $variantId
                            ? $q->where('product_variant_id', $variantId)
                            : $q->whereNull('product_variant_id');
                    })
                    ->where('reason', InventoryMovement::REASON_ORDER_RESERVED)
                    ->sum('change'));

                // إجمالي ما أُعيد سابقًا (موجب) لهذا المنتج/المتغير ضمن نفس الطلب
                $alreadyReturned = (int) InventoryMovement::query()
                    ->where('reference_type', 'order')
                    ->where('reference_id',  $order->id)
                    ->where('product_id',    $productId)
                    ->where(function ($q) use ($variantId) {
                        $variantId
                            ? $q->where('product_variant_id', $variantId)
                            : $q->whereNull('product_variant_id');
                    })
                    ->where('reason', InventoryMovement::REASON_ORDER_CANCELLED)
                    ->sum('change');

                $needReturn = $reserved - $alreadyReturned;

                if ($needReturn > 0) {
                    InventoryMovement::recordMovement(
                        productId: $productId,
                        change:    +$needReturn, // موجب (إرجاع)
                        reason:    InventoryMovement::REASON_ORDER_CANCELLED,
                        variantId: $variantId,
                        reference: $order
                    );
                }
            }
        });
    }
}
