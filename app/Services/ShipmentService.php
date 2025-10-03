<?php
namespace App\Services;

use App\Models\{Order, OrderItem, Shipment, ShipmentItem, ShipmentEvent};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShipmentService
{
    /** إنشاء شحنة فارغة (label_created) */
    public function createShipment(Order $order, ?int $carrierId, ?string $tracking = null): Shipment
    {
        return Shipment::create([
            'order_id'            => $order->id,
            'shipping_carrier_id' => $carrierId,
            'tracking_number'     => $tracking,
            'status'              => 'label_created',
        ]);
    }

    /**
     * إضافة بند للـShipment مع منع الشحن الزائد.
     * - يتحقق أن الـOrderItem يخص نفس الطلب.
     * - يحسب المتبقّي = qty الطلب - مجموع المشحون (باستثناء returned).
     */
    public function addItem(Shipment $shipment, int $orderItemId, int $qty): ShipmentItem
    {
        if ($qty < 1) {
            throw ValidationException::withMessages(['qty' => 'Quantity must be at least 1']);
        }

        return DB::transaction(function () use ($shipment, $orderItemId, $qty) {
            /** @var OrderItem $oi */
            $oi = OrderItem::whereKey($orderItemId)->firstOrFail();

            // تأكد أن البند يعود لنفس طلب الشحنة
            if ($oi->order_id !== $shipment->order_id) {
                throw ValidationException::withMessages(['order_item_id' => 'Order item does not belong to this order']);
            }

            // مجموع المشحون غير الراجِع
            $already = ShipmentItem::query()
                ->where('order_item_id', $oi->id)
                ->whereHas('shipment', fn($q) => $q->where('status', '!=', 'returned'))
                ->sum('qty');

            $remaining = max(0, (int)$oi->qty - (int)$already);
            if ($qty > $remaining) {
                throw ValidationException::withMessages([
                    'qty' => "Exceeds remaining to ship ({$remaining})"
                ]);
            }

            $item = ShipmentItem::create([
                'shipment_id'   => $shipment->id,
                'order_item_id' => $oi->id,
                'qty'           => (int)$qty,
            ]);

            // تحدّيث Fulfillment
            $shipment->refreshOrderFulfillment();

            return $item;
        });
    }

    /**
     * انتقال حالة الشحنة:
     * - in_transit / out_for_delivery ⇒ نضبط shipped_at إن ما كان موجود.
     * - delivered ⇒ نضبط delivered_at.
     * - نسجّل ShipmentEvent.
     */
    public function transition(Shipment $shipment, string $to, ?string $desc = null): Shipment
    {
        // لو عندك StateMachine خاصة خليك عليها؛ وإلا تأكد من حالات الموديل
        $allowed = \App\Models\Shipment::ALLOWED[$shipment->status] ?? [];
        if (!in_array($to, $allowed, true)) {
            throw new \RuntimeException("Invalid shipment transition {$shipment->status} → {$to}");
        }

        return DB::transaction(function () use ($shipment, $to, $desc) {
            $shipment->update(['status' => $to]);

            ShipmentEvent::create([
                'shipment_id' => $shipment->id,
                'code'        => $to,
                'description' => $desc,
                'happened_at' => now(),
            ]);

            if (in_array($to, ['in_transit','out_for_delivery'], true) && is_null($shipment->shipped_at)) {
                $shipment->forceFill(['shipped_at' => now()])->save();
            }
            if ($to === 'delivered' && is_null($shipment->delivered_at)) {
                $shipment->forceFill(['delivered_at' => now()])->save();
            }

            $shipment->refreshOrderFulfillment();
            return $shipment->fresh(['items']);
        });
    }
}
