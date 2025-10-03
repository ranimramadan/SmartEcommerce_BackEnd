<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    protected $fillable = [
        'payment_id',
        'order_id',
        'amount',
        'status',
        'reason',
        'provider_refund_id',
        'idempotency_key',
        'meta',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'meta'   => 'array',
    ];

    // Status Constants
    public const STATUS_PENDING   = 'pending';
    public const STATUS_SUCCEEDED = 'succeeded';
    public const STATUS_FAILED    = 'failed';

    /* ---------- علاقات ---------- */

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    // للوصول السريع لمزوّد الدفع
    public function provider()
    {
        return $this->payment?->provider();
    }

    /* ---------- سكوبات ---------- */

    public function scopePending($q)   { return $q->where('status', self::STATUS_PENDING); }
    public function scopeSucceeded($q) { return $q->where('status', self::STATUS_SUCCEEDED); }
    public function scopeFailed($q)    { return $q->where('status', self::STATUS_FAILED); }

    public function scopeByOrder($q, $orderId)
    {
        return $q->where('order_id', $orderId);
    }

    /* ---------- مساعدات ---------- */

    public function isPending(): bool   { return $this->status === self::STATUS_PENDING; }
    public function isSucceeded(): bool { return $this->status === self::STATUS_SUCCEEDED; }
    public function isFailed(): bool    { return $this->status === self::STATUS_FAILED; }

    public function getIsPartialRefundAttribute(): bool
    {
        $paymentAmount = (float) ($this->payment->amount ?? 0);
        return (float)$this->amount < $paymentAmount && $paymentAmount > 0;
    }

    public function getFormattedAmountAttribute(): string
    {
        $cur = $this->payment->currency ?? 'USD';
        return number_format((float)$this->amount, 2) . ' ' . $cur;
    }

    public function markAsSucceeded(?string $providerRefundId = null): self
    {
        $this->status = self::STATUS_SUCCEEDED;
        if ($providerRefundId) {
            $this->provider_refund_id = $providerRefundId;
        }
        $this->save();
        return $this;
    }

    public function markAsFailed(?string $reason = null): self
    {
        $this->status = self::STATUS_FAILED;
        if ($reason) {
            $this->reason = $reason;
        }
        $this->save();
        return $this;
    }
}
