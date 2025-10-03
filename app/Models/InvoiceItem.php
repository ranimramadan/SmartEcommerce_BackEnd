<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id',
        'order_item_id',
        'product_name',
        'unit_price',
        'qty',
        'discount_amount',
        'tax_amount',
        'line_total',
    ];

    protected $casts = [
        'unit_price'       => 'decimal:2',
        'qty'              => 'integer',
        'discount_amount'  => 'decimal:2',
        'tax_amount'       => 'decimal:2',
        'line_total'       => 'decimal:2',
    ];

    /* ------------------------- Relationships ------------------------- */

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }

    /* --------------------------- Helpers ---------------------------- */

    public function calculateTotals()
    {
        $this->line_total = ($this->unit_price * $this->qty) - $this->discount_amount + $this->tax_amount;
        $this->save();

        return $this;
    }

    public function updateQuantity(int $qty)
    {
        $this->qty = $qty;
        return $this->calculateTotals();
    }

    public function applyDiscount($amount)
    {
        $this->discount_amount = min($amount, $this->unit_price * $this->qty);
        return $this->calculateTotals();
    }

    public function applyTax($amount)
    {
        $this->tax_amount = $amount;
        return $this->calculateTotals();
    }

    public static function createFromOrderItem($invoiceId, $orderItem, $qty = null)
    {
        return static::create([
            'invoice_id'      => $invoiceId,
            'order_item_id'   => $orderItem->id,
            'product_name'    => $orderItem->name,
            'unit_price'      => $orderItem->price,
            'qty'             => $qty ?? $orderItem->qty,
            'discount_amount' => 0,
            'tax_amount'      => 0,
        ])->calculateTotals();
    }

    /* -------------------------- Accessors --------------------------- */

    public function getSubtotalAttribute()
    {
        return $this->unit_price * $this->qty;
    }

    public function getFormattedUnitPriceAttribute()
    {
        return number_format($this->unit_price, 2);
    }

    public function getFormattedLineTotalAttribute()
    {
        return number_format($this->line_total, 2);
    }

    public function getFormattedDiscountAttribute()
    {
        return number_format($this->discount_amount, 2);
    }

    public function getFormattedTaxAttribute()
    {
        return number_format($this->tax_amount, 2);
    }

    /* ---------------------------- Hooks ----------------------------- */

    protected static function booted()
    {
        // قبل الإنشاء: تأكيد الكمية + حساب line_total إن لزم
        static::creating(function (InvoiceItem $item) {
            self::guardQty($item, (int) $item->qty);

            if (is_null($item->line_total)) {
                $item->line_total = ($item->unit_price * $item->qty)
                    - $item->discount_amount + $item->tax_amount;
            }
        });

        // قبل التحديث: لو تغيّرت الكمية (أو الحقول المؤثّرة) نعمل تحقّق/إعادة حساب
        static::updating(function (InvoiceItem $item) {
            if ($item->isDirty('qty')) {
                self::guardQty($item, (int) $item->qty, (int) $item->getOriginal('qty'));
            }

            if ($item->isDirty(['unit_price', 'qty', 'discount_amount', 'tax_amount'])) {
                $item->line_total = ($item->unit_price * $item->qty)
                    - $item->discount_amount + $item->tax_amount;
            }
        });

        // أي تغيير على البنود يحدّث مجاميع الفاتورة
        static::created(fn ($item) => $item->invoice?->updateTotals());
        static::updated(fn ($item) => $item->invoice?->updateTotals());
        static::deleted(fn ($item) => $item->invoice?->updateTotals());
    }

    /**
     * يمنع تجاوز مجموع الكميات المفوترة للـ order_item عن qty الأصلي
     */
    protected static function guardQty(InvoiceItem $item, int $newQty, ?int $oldQty = null): void
    {
        if ($newQty < 1) {
            throw new \InvalidArgumentException('Invoice item qty must be >= 1');
        }

        // الـ order_item الأصلي
        $orderItem = $item->orderItem()->select('id', 'qty')->first();
        if (!$orderItem) {
            throw new \RuntimeException('Order item not found for invoicing.');
        }

        // مجموع ما فُوتر لهذا الـ order_item عبر كل الفواتير (استثني السطر الحالي لو محدث)
        $alreadyInvoiced = static::where('order_item_id', $orderItem->id)
            ->when($item->exists, fn ($q) => $q->where('id', '!=', $item->id))
            ->sum('qty');

        $newTotal = (int) $alreadyInvoiced + (int) $newQty;
        if ($newTotal > (int) $orderItem->qty) {
            throw new \RuntimeException('Invoiced quantity exceeds ordered quantity.');
        }
    }
}
