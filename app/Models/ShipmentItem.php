<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\OrderItem; // ✅ مفقودة سابقًا

class ShipmentItem extends Model
{
    protected $fillable = ['shipment_id','order_item_id','qty'];
    protected $casts    = ['qty' => 'integer'];
    protected $appends  = ['product_name','sku']; // يطلعوا تلقائياً في JSON

    // علاقات
    public function shipment()   { return $this->belongsTo(Shipment::class); }
    public function orderItem()  { return $this->belongsTo(OrderItem::class); }

    // Accessors لراحة الفرونت
    public function getProductNameAttribute(): ?string { return $this->orderItem?->name; }
    public function getSkuAttribute(): ?string         { return $this->orderItem?->sku; }

    /** حساب كمية مشحونة لبند طلب (يستثني الشحنات الراجعة) */
    private static function shippedQtyExcludingReturned(int $orderItemId, ?int $exceptShipmentItemId = null): int
    {
        $q = static::query()
            ->where('order_item_id', $orderItemId)
            ->whereHas('shipment', fn($s) => $s->where('status', '!=', Shipment::STATUS_RETURNED));

        if ($exceptShipmentItemId) {
            $q->where('id', '!=', $exceptShipmentItemId);
        }

        return (int) $q->sum('qty');
    }

    /** هل الكمية الجديدة مسموحة (لا تتجاوز المتبقّي) */
    public function canUpdateQuantity(int $newQty): bool
    {
        if ($newQty < 1) return false;

        $oi = $this->orderItem()->first(); // نتأكد من القراءة من DB
        if (!$oi) return false;

        $alreadyOther = self::shippedQtyExcludingReturned($oi->id, $this->id);
        $maxAllowed   = (int) $oi->qty - $alreadyOther;

        return $newQty <= $maxAllowed;
    }

    public function updateQuantity(int $newQty): bool
    {
        if (! $this->canUpdateQuantity($newQty)) return false;
        $this->update(['qty' => $newQty]);
        return true;
    }

    // مساعد: إنشاء من بند الطلب
    public static function createFromOrderItem(int $shipmentId, OrderItem $orderItem, ?int $qty = null): self
    {
        // المتبقّي = qty الطلب - مجموع المشحون (ما عدا returned)
        $already = self::shippedQtyExcludingReturned($orderItem->id);
        $remaining = max(0, (int) $orderItem->qty - $already);

        $qty = $qty ?? $remaining;
        if ($qty < 1 || $qty > $remaining) {
            throw new \RuntimeException('Invalid shipping quantity.');
        }

        return static::create([
            'shipment_id'    => $shipmentId,
            'order_item_id'  => $orderItem->id,
            'qty'            => $qty,
        ]);
    }

    protected static function booted(): void
    {
        // قبل الإنشاء: تحققّات سلامة
        static::creating(function (ShipmentItem $item) {
            if ($item->qty < 1) {
                throw new \InvalidArgumentException('Quantity must be at least 1.');
            }

            $shipment  = $item->shipment()->first();
            $orderItem = $item->orderItem()->first();

            if ($shipment && $orderItem && $shipment->order_id !== $orderItem->order_id) {
                throw new \RuntimeException('Order item does not belong to the same order as the shipment.');
            }

            // لا تتجاوز المتبقّي (نحسبه بدون الاعتماد على remaining_qty)
            if ($orderItem) {
                $already   = self::shippedQtyExcludingReturned($orderItem->id);
                $remaining = max(0, (int) $orderItem->qty - $already);
                if ($item->qty > $remaining) {
                    throw new \RuntimeException('Shipping quantity exceeds remaining quantity.');
                }
            }
        });

        // قبل التعديل: لا تتجاوز الحدّ المسموح
        static::updating(function (ShipmentItem $item) {
            if (! $item->canUpdateQuantity((int) $item->qty)) {
                throw new \RuntimeException('New quantity exceeds remaining quantity.');
            }
        });

        // بعد الحفظ/الحذف: حدّث Fulfillment الطلب (partial/fulfilled)
        static::saved(function (ShipmentItem $item) {
            $item->shipment?->refreshOrderFulfillment();
        });
        static::deleted(function (ShipmentItem $item) {
            $item->shipment?->refreshOrderFulfillment();
        });
    }
}
