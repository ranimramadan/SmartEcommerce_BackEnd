<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Invoice extends Model
{
    protected $fillable = [
        'order_id',
        'invoice_no',
        'status',
        'currency',
        'subtotal',
        'discount_total',
        'tax_total',
        'shipping_total',
        'grand_total',
        'issued_at',
        'due_at',
        'paid_at',
        'pdf_path',
        'notes',
    ];

    protected $casts = [
        'subtotal'        => 'decimal:2',
        'discount_total'  => 'decimal:2',
        'tax_total'       => 'decimal:2',
        'shipping_total'  => 'decimal:2',
        'grand_total'     => 'decimal:2',
        'issued_at'       => 'datetime',
        'due_at'          => 'datetime',
        'paid_at'         => 'datetime',
    ];

    protected $appends = ['formatted_grand_total', 'pdf_url', 'due_days'];

    // حالات
    public const STATUS_DRAFT    = 'draft';
    public const STATUS_ISSUED   = 'issued';
    public const STATUS_PAID     = 'paid';
    public const STATUS_VOID     = 'void';
    public const STATUS_REFUNDED = 'refunded';

    /* ------------ علاقات ------------ */
    public function order()    { return $this->belongsTo(Order::class); }
    public function items()    { return $this->hasMany(InvoiceItem::class); }
    public function payments() { return $this->hasMany(Payment::class); } // payments.invoice_id nullable

    /* ------------ سكوبات ------------ */
    public function scopeDraft($q)   { return $q->where('status', self::STATUS_DRAFT); }
    public function scopeIssued($q)  { return $q->where('status', self::STATUS_ISSUED); }
    public function scopePaid($q)    { return $q->where('status', self::STATUS_PAID); }
    public function scopeUnpaid($q)  { return $q->whereNotIn('status', [self::STATUS_PAID, self::STATUS_VOID]); }
    public function scopeOverdue($q)
    {
        return $q->where('status', self::STATUS_ISSUED)
                 ->whereNotNull('due_at')
                 ->where('due_at', '<', now());
    }

    /* ------------ Transitions ------------ */
    public function issue(): self
    {
        if ($this->status === self::STATUS_DRAFT) {
            $this->forceFill([
                'status'    => self::STATUS_ISSUED,
                'issued_at' => now(),
            ])->save();
        }
        return $this;
    }

    public function markAsPaid(): self
    {
        if (in_array($this->status, [self::STATUS_ISSUED, self::STATUS_DRAFT], true)) {
            $this->forceFill([
                'status'  => self::STATUS_PAID,
                'paid_at' => now(),
            ])->save();
        }
        return $this;
    }

    public function void(): self
    {
        if (! in_array($this->status, [self::STATUS_PAID, self::STATUS_REFUNDED], true)) {
            $this->update(['status' => self::STATUS_VOID]);
        }
        return $this;
    }

    public function markAsRefunded(): self
    {
        if ($this->status === self::STATUS_PAID) {
            $this->update(['status' => self::STATUS_REFUNDED]);
        }
        return $this;
    }

    /* ------------ Checks ------------ */
    public function isDraft(): bool  { return $this->status === self::STATUS_DRAFT; }
    public function isIssued(): bool { return $this->status === self::STATUS_ISSUED; }
    public function isPaid(): bool   { return $this->status === self::STATUS_PAID; }
    public function isOverdue(): bool
    {
        return $this->isIssued() && $this->due_at && $this->due_at->isPast();
    }

    /* ------------ Accessors ------------ */
    public function getFormattedGrandTotalAttribute(): string
    {
        return number_format($this->grand_total, 2) . ' ' . $this->currency;
    }

    public function getPdfUrlAttribute(): ?string
    {
        return $this->pdf_path ? Storage::url($this->pdf_path) : null;
    }

    public function getDueDaysAttribute(): ?int
    {
        return $this->due_at ? now()->diffInDays($this->due_at, false) : null;
    }

    /* ------------ مجاميع الفاتورة ------------ */
    public function updateTotals(): void
    {
        $row = $this->items()
            ->selectRaw('
                COALESCE(SUM(unit_price * qty), 0) AS subtotal,
                COALESCE(SUM(discount_amount), 0) AS discount_total,
                COALESCE(SUM(tax_amount), 0)      AS tax_total
            ')
            ->first();

        $shipping = (float) ($this->shipping_total ?? 0);
        $subtotal = (float) $row->subtotal;
        $discount = (float) $row->discount_total;
        $tax      = (float) $row->tax_total;

        $this->forceFill([
            'subtotal'       => $subtotal,
            'discount_total' => $discount,
            'tax_total'      => $tax,
            'grand_total'    => max(0, $subtotal - $discount + $tax + $shipping),
        ])->saveQuietly();
    }
}
