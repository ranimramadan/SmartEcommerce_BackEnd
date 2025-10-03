<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Order; // لاستخدامه في convertToOrder

class CouponRedemption extends Model
{
    protected $fillable = [
        'coupon_id',
        'user_id',
        'cart_id',
        'order_id',
        'amount',
        'used_at',
    ];

    protected $casts = [
        'amount'  => 'decimal:2',
        'used_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | علاقات
    |--------------------------------------------------------------------------
    */
    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByCoupon($query, $couponId)
    {
        return $query->where('coupon_id', $couponId);
    }

    public function scopeUsed($query)
    {
        return $query->whereNotNull('used_at');
    }

    public function scopeForOrders($query)
    {
        return $query->whereNotNull('order_id');
    }

    public function scopeForCarts($query)
    {
        return $query->whereNotNull('cart_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */
    /** تحديد وقت الاستخدام (مرة واحدة فقط) */
    public function markAsUsed(): self
    {
        if (is_null($this->used_at)) {
            $this->used_at = now();
            $this->save();
        }
        return $this;
    }

    /**
     * تحويل الاسترداد من Cart إلى Order عند الإكمال (Checkout)
     * - يربط الـ redemption بالطلب
     * - يلغي الربط بالسلة
     * - يضبط used_at
     */
    public function convertToOrder(Order $order): self
    {
        if ($this->cart_id && !$this->order_id) {
            $this->order_id = $order->id;
            $this->cart_id  = null;
            $this->markAsUsed();
        }
        return $this;
    }

    /** هل تمّ استخدامه؟ */
    public function isUsed(): bool
    {
        return !is_null($this->used_at);
    }

    /** هل يخص سلة؟ */
    public function isForCart(): bool
    {
        return !is_null($this->cart_id);
    }

    /** هل يخص طلب؟ */
    public function isForOrder(): bool
    {
        return !is_null($this->order_id);
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */
    public function getFormattedAmountAttribute(): string
    {
        return number_format((float) $this->amount, 2);
    }

    public function getSourceTypeAttribute(): string
    {
        if ($this->order_id) return 'Order';
        if ($this->cart_id)  return 'Cart';
        return 'Unknown';
    }

    public function getSourceIdAttribute()
    {
        return $this->order_id ?? $this->cart_id;
    }

    /*
    |--------------------------------------------------------------------------
    | حراسة سلامة البيانات (XOR Cart/Order)
    |--------------------------------------------------------------------------
    | لازم السطر يشير إلى مصدر واحد فقط: إمّا Cart أو Order.
    | (DB CHECK قد لا يكون متاح بكل الإصدارات، فنضمنه على مستوى التطبيق)
    */
    protected static function booted(): void
    {
        static::saving(function (self $r) {
            $hasOrder = !is_null($r->order_id);
            $hasCart  = !is_null($r->cart_id);

            // يجب أن يكون واحد فقط true
            if ($hasOrder === $hasCart) {
                throw new \InvalidArgumentException(
                    'CouponRedemption must link to exactly one source: Cart OR Order.'
                );
            }
        });
    }
}
