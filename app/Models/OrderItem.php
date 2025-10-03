<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'product_variant_id',
        'sku',
        'name',
        'price',
        'qty',
        'line_subtotal',
        'line_discount',
        'line_total',
        'options',
    ];

    protected $casts = [
        'price'          => 'decimal:2',
        'line_subtotal'  => 'decimal:2',
        'line_discount'  => 'decimal:2',
        'line_total'     => 'decimal:2',
        'options'        => 'array',
        'qty'            => 'integer',
    ];

    // لإرجاع الخصائص المحسوبة تلقائياً ضمن JSON
    protected $appends = ['shipped_qty', 'remaining_qty', 'is_fully_shipped'];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */
    public function order()           { return $this->belongsTo(Order::class); }
    public function product()         { return $this->belongsTo(Product::class); }
    public function productVariant()  { return $this->belongsTo(ProductVariant::class); }
    public function shipmentItems()   { return $this->hasMany(ShipmentItem::class); }
    public function invoiceItems()    { return $this->hasMany(InvoiceItem::class); }

    /*
    |--------------------------------------------------------------------------
    | Accessors (Computed)
    |--------------------------------------------------------------------------
    */

    // اجتناب N+1: نستخدم withSum لو متوفر، وإلا نجري SUM سريع
    public function getShippedQtyAttribute(): int
    {
        // لو جايبة withSum('shipmentItems as shipment_items_sum_qty', 'qty')
        if (array_key_exists('shipment_items_sum_qty', $this->attributes)) {
            return (int) ($this->attributes['shipment_items_sum_qty'] ?? 0);
        }

        // fallback آمن (Query واحد للسطر)
        return (int) $this->shipmentItems()->sum('qty');
    }

    public function getRemainingQtyAttribute(): int
    {
        $rem = (int) $this->qty - (int) $this->shipped_qty;
        return max(0, $rem);
    }

    public function getIsFullyShippedAttribute(): bool
    {
        return $this->remaining_qty <= 0;
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */
    public function scopeForOrder($q, int $orderId)
    {
        return $q->where('order_id', $orderId);
    }

    public function scopeNotFullyShipped($q)
    {
        return $q->whereColumn('qty', '>', 'qty_shipped_placeholder'); // للاستخدام مع withSum (تحت)
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */
    public function calculateTotals(): self
    {
        $qty            = max(1, (int) $this->qty);
        $price          = (float) $this->price;
        $line_subtotal  = $price * $qty;
        $line_discount  = (float) ($this->line_discount ?? 0);
        $line_total     = $line_subtotal - $line_discount;

        $this->qty            = $qty;
        $this->line_subtotal  = $line_subtotal;
        $this->line_total     = max(0, $line_total);

        return $this;
    }

    public function canBeShipped(): bool
    {
        return $this->remaining_qty > 0;
    }

    /*
    |--------------------------------------------------------------------------
    | Events
    |--------------------------------------------------------------------------
    */
    protected static function booted()
    {
        // عند الإنشاء
        static::creating(function (OrderItem $item) {
            // لو ما انحسبوا، نحسبهم
            if (is_null($item->line_subtotal) || is_null($item->line_total)) {
                $item->calculateTotals();
            }
        });

        // عند الحفظ (يشمل التحديث)
        static::saving(function (OrderItem $item) {
            // لو تغيّر السعر/الكمية/الخصم، نعيد الحساب
            if ($item->isDirty(['price','qty','line_discount'])) {
                $item->calculateTotals();
            }
        });
    }
}
