<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'order_id',
        'payment_provider_id',
        'invoice_id',        // <- أضفناه لأن عندك migration يضيف العمود
        'idempotency_key',
        'transaction_id',
        'status',
        'amount',
        'currency',
        'raw_response',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'raw_response' => 'array',
    ];

    // Status Constants
    public const STATUS_AUTHORIZED = 'authorized';
    public const STATUS_CAPTURED   = 'captured';
    public const STATUS_REFUNDED   = 'refunded';
    public const STATUS_FAILED     = 'failed';

    /* --------------------------- العلاقات --------------------------- */

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function provider()
    {
        return $this->belongsTo(PaymentProvider::class, 'payment_provider_id');
    }

    public function refunds()
    {
        return $this->hasMany(Refund::class);
    }

    public function invoice()
    {
        // عندك migration add_invoice_id_to_payments_table
        return $this->belongsTo(Invoice::class);
    }

    /* --------------------------- سكوبات --------------------------- */

    /** نجاح مالي نهائي (تحصيل) */
    public function scopeCaptured($q)
    {
        return $q->where('status', self::STATUS_CAPTURED);
    }

    /** نجاح أولي (أوثرايزيشن أو كابتشر) — حسب منطقك */
    public function scopeSuccessful($q)
    {
        return $q->whereIn('status', [self::STATUS_AUTHORIZED, self::STATUS_CAPTURED]);
    }

    public function scopeFailed($q)
    {
        return $q->where('status', self::STATUS_FAILED);
    }

    public function scopeRefunded($q)
    {
        return $q->where('status', self::STATUS_REFUNDED);
    }

    public function scopeByProvider($q, $providerId)
    {
        return $q->where('payment_provider_id', $providerId);
    }

    /* --------------------------- مساعدات --------------------------- */

    public function isSuccessful(): bool
    {
        return in_array($this->status, [self::STATUS_AUTHORIZED, self::STATUS_CAPTURED], true);
    }

    public function isCaptured(): bool
    {
        return $this->status === self::STATUS_CAPTURED;
    }

    public function isRefunded(): bool
    {
        return $this->status === self::STATUS_REFUNDED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function markAsCaptured(?string $transactionId = null): self
    {
        $this->status = self::STATUS_CAPTURED;
        if ($transactionId) {
            $this->transaction_id = $transactionId;
        }
        $this->save();

        return $this;
    }

    public function markAsFailed($response = null): self
    {
        $this->status = self::STATUS_FAILED;
        if ($response !== null) {
            $this->raw_response = $response;
        }
        $this->save();

        return $this;
    }

    /**
     * بدل الـ accessor اللي كان فيه بارامتر:
     * نستخدم method للقراءة من raw_response.
     */
    public function response(string $key = null, $default = null)
    {
        $data = $this->raw_response ?? [];
        return $key ? data_get($data, $key, $default) : $data;
    }

    public function getFormattedAmountAttribute(): string
    {
        return number_format((float)$this->amount, 2) . ' ' . $this->currency;
    }
}
