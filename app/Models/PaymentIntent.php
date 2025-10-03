<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentIntent extends Model
{
    protected $fillable = [
        'order_id',
        'payment_provider_id',
        'provider_payment_id',
        'client_secret',
        'idempotency_key',
        'status',
        'amount',
        'currency',
        'meta',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'meta'   => 'array',
    ];

    // Status Constants
    public const STATUS_REQUIRES_PAYMENT_METHOD = 'requires_payment_method';
    public const STATUS_REQUIRES_CONFIRMATION   = 'requires_confirmation';
    public const STATUS_PROCESSING              = 'processing';
    public const STATUS_SUCCEEDED               = 'succeeded';
    public const STATUS_CANCELED                = 'canceled';
    public const STATUS_FAILED                  = 'failed';

    /* ---------- علاقات ---------- */

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function provider()
    {
        return $this->belongsTo(PaymentProvider::class, 'payment_provider_id');
    }

    /* ---------- سكوبات ---------- */

    public function scopePending($q)
    {
        return $q->whereIn('status', [
            self::STATUS_REQUIRES_PAYMENT_METHOD,
            self::STATUS_REQUIRES_CONFIRMATION,
            self::STATUS_PROCESSING,
        ]);
    }

    public function scopeSucceeded($q)
    {
        return $q->where('status', self::STATUS_SUCCEEDED);
    }

    public function scopeFailed($q)
    {
        return $q->whereIn('status', [self::STATUS_CANCELED, self::STATUS_FAILED]);
    }

    /* ---------- مساعدات ---------- */

    public function isPending(): bool
    {
        return in_array($this->status, [
            self::STATUS_REQUIRES_PAYMENT_METHOD,
            self::STATUS_REQUIRES_CONFIRMATION,
            self::STATUS_PROCESSING,
        ], true);
    }

    public function isSucceeded(): bool
    {
        return $this->status === self::STATUS_SUCCEEDED;
    }

    public function isFailed(): bool
    {
        return in_array($this->status, [self::STATUS_CANCELED, self::STATUS_FAILED], true);
    }

    public function markAsSucceeded(): self
    {
        $this->status = self::STATUS_SUCCEEDED;
        $this->save();
        return $this;
    }

    public function markAsFailed(): self
    {
        $this->status = self::STATUS_FAILED;
        $this->save();
        return $this;
    }

    public function updateStatus(string $status, ?array $metadata = null): self
    {
        $this->status = $status;
        if ($metadata) {
            $this->meta = array_merge($this->meta ?? [], $metadata);
        }
        $this->save();
        return $this;
    }

    // بدل الـ accessor اللي كان فيه بارامتر
    public function meta(string $key = null, $default = null)
    {
        return $key ? data_get($this->meta ?? [], $key, $default) : ($this->meta ?? []);
    }

    public function getFormattedAmountAttribute(): string
    {
        return number_format((float)$this->amount, 2) . ' ' . $this->currency;
    }
}
