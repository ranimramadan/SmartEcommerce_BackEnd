<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'order_number',
        'user_id',
        'cart_id',
        'coupon_id',

        'status',
        'payment_status',
        'fulfillment_status',

        'subtotal',
        'discount_total',
        'shipping_total',
        'tax_total',
        'grand_total',
        'currency',

        // Snapshot من add_coupon_snapshot_to_orders_table
        'coupon_code',
        'coupon_type',          // percent | amount | free_shipping
        'coupon_value',         // رقم أو نسبة
        'coupon_max_discount',
        'coupon_free_shipping',
        'coupon_discount',

        'payment_provider_id',
    ];

    protected $casts = [
        'subtotal'            => 'decimal:2',
        'discount_total'      => 'decimal:2',
        'shipping_total'      => 'decimal:2',
        'tax_total'           => 'decimal:2',
        'grand_total'         => 'decimal:2',

        'coupon_value'        => 'decimal:2',
        'coupon_max_discount' => 'decimal:2',
        'coupon_discount'     => 'decimal:2',
        'coupon_free_shipping'=> 'boolean',
    ];

    /* علاقات */
    public function user()            { return $this->belongsTo(User::class)->withDefault(); }
    public function cart()            { return $this->belongsTo(Cart::class); }
    public function coupon()          { return $this->belongsTo(Coupon::class); }
    public function paymentProvider() { return $this->belongsTo(PaymentProvider::class); }

    public function items()           { return $this->hasMany(OrderItem::class); }
    public function addresses()       { return $this->hasMany(OrderAddress::class); }
    public function statusEvents()    { return $this->hasMany(OrderStatusEvent::class); }
    public function shipments()       { return $this->hasMany(Shipment::class); }
    public function payments()        { return $this->hasMany(Payment::class); }
    public function refunds()         { return $this->hasMany(Refund::class); } // عندك order_id بالـrefunds

    // فواتير
    public function invoices()        { return $this->hasMany(Invoice::class); }
    public function invoice()         { return $this->hasOne(Invoice::class)->latestOfMany(); } // أحدث فاتورة

    // حركات المخزون (Polymorphic)
    public function inventoryMovements()
    {
        return $this->morphMany(\App\Models\InventoryMovement::class, 'reference');
    }

    // عناوين
    public function shippingAddress() { return $this->hasOne(OrderAddress::class)->where('type','shipping'); }
    public function billingAddress()  { return $this->hasOne(OrderAddress::class)->where('type','billing'); }

    /* Scopes */
    public function scopePending($q)   { return $q->where('status', 'placed'); }
    public function scopeUnpaid($q)    { return $q->where('payment_status', 'unpaid'); }
    public function scopeDelivered($q) { return $q->where('status', 'delivered'); }

    /* Accessors */
    public function getIsCompletedAttribute(): bool
    {
        return in_array($this->status, ['delivered', 'cancelled', 'returned'], true);
    }
    public function getIsCancelledAttribute(): bool { return $this->status === 'cancelled'; }
    public function getIsReturnedAttribute(): bool  { return $this->status === 'returned'; }

    /* Helpers */
    public function canBeCancelled(): bool
    { return !$this->is_completed && $this->payment_status !== 'paid'; }

    public function canBeEdited(): bool
    { return $this->status === 'placed'; }

    public static function generateOrderNumber(): string
    {
        do {
            $number = 'ORD-' . strtoupper(substr(uniqid('', true), -8));
        } while (static::where('order_number', $number)->exists());
        return $number;
    }

    /** (اختياري) لو حبيتي تناديه يدويًا من الكنترولر */
    public function refreshFulfillmentStatus(): void
    {
        $this->loadMissing([
            'items:id,order_id,qty',
            'shipments:id,order_id,status',
            'shipments.items:id,shipment_id,order_item_id,qty'
        ]);

        $orderedQty = (int) $this->items->sum('qty');

        $countableStatuses = [
            Shipment::STATUS_IN_TRANSIT,
            Shipment::STATUS_OUT_FOR_DELIVERY,
            Shipment::STATUS_DELIVERED,
        ];

        $shippedQty = (int) $this->shipments
            ->whereIn('status', $countableStatuses)
            ->flatMap->items
            ->sum('qty');

        $new = $orderedQty === 0 ? 'unfulfilled'
            : ($shippedQty === 0 ? 'unfulfilled' : ($shippedQty < $orderedQty ? 'partial' : 'fulfilled'));

        if ($this->fulfillment_status !== $new) {
            $this->update(['fulfillment_status' => $new]);
        }
    }
}
