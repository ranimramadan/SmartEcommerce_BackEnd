<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{

    protected $fillable = [
    'order_id','shipping_carrier_id','tracking_number','status',
    'shipped_at','delivered_at','failure_reason', // ✅
];

    protected $casts = [
        'shipped_at'   => 'datetime',
        'delivered_at' => 'datetime',
    ];

    // نضيف هالحقول تلقائيًا في JSON
    protected $appends = ['status_label', 'tracking_url'];

    // حالات
    public const STATUS_LABEL_CREATED     = 'label_created';
    public const STATUS_IN_TRANSIT        = 'in_transit';
    public const STATUS_OUT_FOR_DELIVERY  = 'out_for_delivery';
    public const STATUS_DELIVERED         = 'delivered';
    public const STATUS_FAILED            = 'failed';
    public const STATUS_RETURNED          = 'returned';

    // انتقالات مسموحة
    public const ALLOWED = [
        self::STATUS_LABEL_CREATED    => [self::STATUS_IN_TRANSIT, self::STATUS_FAILED],
        self::STATUS_IN_TRANSIT       => [self::STATUS_OUT_FOR_DELIVERY, self::STATUS_FAILED, self::STATUS_RETURNED],
        self::STATUS_OUT_FOR_DELIVERY => [self::STATUS_DELIVERED, self::STATUS_FAILED, self::STATUS_RETURNED],
        self::STATUS_DELIVERED        => [],
        self::STATUS_FAILED           => [],
        self::STATUS_RETURNED         => [],
    ];

    // علاقات
    public function order()   { return $this->belongsTo(Order::class); }
    public function carrier() { return $this->belongsTo(ShippingCarrier::class, 'shipping_carrier_id'); }
    public function items()   { return $this->hasMany(ShipmentItem::class); }
    public function events()  { return $this->hasMany(ShipmentEvent::class); }

    // حركات المخزون (Polymorphic)
    public function inventoryMovements()
    {
        return $this->morphMany(\App\Models\InventoryMovement::class, 'reference');
    }

    // سكوبات
    public function scopeDelivered($q) { return $q->where('status', self::STATUS_DELIVERED); }
    public function scopePending($q)   { return $q->where('status', self::STATUS_LABEL_CREATED); }
    public function scopeInTransit($q) { return $q->whereIn('status', [self::STATUS_IN_TRANSIT, self::STATUS_OUT_FOR_DELIVERY]); }

    // Accessors
    public function getStatusLabelAttribute(): string
    {
        return ucwords(str_replace('_', ' ', $this->status));
    }

    public function getTrackingUrlAttribute(): ?string
    {
        return $this->carrier?->getTrackingUrl($this->tracking_number);
    }

    // Helpers
    public function isDelivered(): bool
    {
        return $this->status === self::STATUS_DELIVERED;
    }

    public function isInTransit(): bool
    {
        return in_array($this->status, [self::STATUS_IN_TRANSIT, self::STATUS_OUT_FOR_DELIVERY], true);
    }

    public function markAsShipped(): void
    {
        $this->transitionTo(self::STATUS_IN_TRANSIT);
        if (!$this->shipped_at) {
            $this->forceFill(['shipped_at' => now()])->save();
        }
    }

    public function markAsDelivered(): void
    {
        $this->transitionTo(self::STATUS_DELIVERED);
        $this->forceFill(['delivered_at' => now()])->save();
    }

    /**
     * انتقال حالة مضبوط
     */
    public function transitionTo(string $toStatus, ?string $note = null): void
    {
        $from = $this->status;
        if (!in_array($toStatus, self::ALLOWED[$from] ?? [], true)) {
            throw new \RuntimeException("Invalid shipment transition: $from → $toStatus");
        }

        $this->update(['status' => $toStatus]);

        // ✅ مطابق للميغريشن: code/description/happened_at
        $this->events()->create([
            'code'        => $toStatus,
            'description' => $note,
            'happened_at' => now(),
        ]);

        // حدّث Fulfillment للطلب
        $this->refreshOrderFulfillment();
    }

    /**
     * يحدّث Fulfillment للطلب بناءً على الكميات المشحونة
     */
    public function refreshOrderFulfillment(): void
    {
        $order = $this->order()->with([
            'items:id,order_id,qty',
            'shipments:id,order_id,status',
            'shipments.items:id,shipment_id,order_item_id,qty'
        ])->first();

        if (! $order) return;

        $orderedQty = (int) $order->items->sum('qty');

        $countableStatuses = [
            self::STATUS_IN_TRANSIT,
            self::STATUS_OUT_FOR_DELIVERY,
            self::STATUS_DELIVERED,
            // لو بدك تحسبي المرتجع كشحن سابق: self::STATUS_RETURNED,
        ];

        $shippedQty = (int) $order->shipments
            ->whereIn('status', $countableStatuses)
            ->flatMap->items
            ->sum('qty');

        $new = 'unfulfilled';
        if ($orderedQty > 0) {
            $new = $shippedQty === 0
                ? 'unfulfilled'
                : ($shippedQty < $orderedQty ? 'partial' : 'fulfilled');
        }

        if ($order->fulfillment_status !== $new) {
            $order->update(['fulfillment_status' => $new]);
        }
    }
     public static function generateInternalTracking(): string
    {
        return 'INT-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6));
    }
}
