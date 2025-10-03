<?php
namespace App\Services;

use App\Models\Order;
use App\Models\OrderStatusEvent;
use App\Support\StateMachines\OrderState;
use Illuminate\Support\Facades\DB;

class OrderService
{
    /**
     * سجّل حدث تغيير الحالة (timeline) مع تتبّع مَن غيّرها.
     *
     * $actorId اختياري:
     *  - لو null نحاول ناخدها من auth()->id()
     *  - بالويبهوك أو الكرون: مرّري ID نظامي أو اتركيها null
     */
    public function recordStatus(Order $order, string $status, ?string $note = null, ?int $actorId = null): void
    {
        $actorId = $actorId ?? (auth()->check() ? auth()->id() : null);

        OrderStatusEvent::create([
            'order_id'      => $order->id,
            'status'        => $status,
            'note'          => $note,
            'happened_at'   => now(),
            'changed_by_id' => $actorId,   // 👈 هون التخزين
        ]);
    }

    /**
     * انتقال حالة الطلب مع حفظ الحدث.
     */
    public function transition(Order $order, string $to, ?string $note = null, ?int $actorId = null): Order
    {
        if (! OrderState::canTransition($order->status, $to)) {
            throw new \RuntimeException("Invalid order transition {$order->status} → {$to}");
        }

        return DB::transaction(function () use ($order, $to, $note, $actorId) {
            $order->update(['status' => $to]);
            $this->recordStatus($order, $to, $note, $actorId);

            if ($to === 'cancelled') {
                app(InventoryService::class)->releaseOrder($order);
            }

            return $order->fresh();
        });
    }
}
