<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    protected $fillable = [
        'user_id','session_id','coupon_id','item_count',
        'subtotal','discount_total','shipping_total','tax_total','grand_total',
        'currency','status','expires_at',
    ];

    protected $casts = [
        'item_count'     => 'integer',
        'subtotal'       => 'decimal:2',
        'discount_total' => 'decimal:2',
        'shipping_total' => 'decimal:2',
        'tax_total'      => 'decimal:2',
        'grand_total'    => 'decimal:2',
        'expires_at'     => 'datetime',
    ];

    public const STATUS_ACTIVE    = 'active';
    public const STATUS_CONVERTED = 'converted';
    public const STATUS_ABANDONED = 'abandoned';

    /* ---------- علاقات ---------- */
    public function user()   { return $this->belongsTo(User::class); }
    public function items()  { return $this->hasMany(CartItem::class); }
    public function coupon() { return $this->belongsTo(Coupon::class); }

    /* ---------- سكوبات ---------- */
    public function scopeActive($q)    { return $q->where('status', self::STATUS_ACTIVE); }
    public function scopeAbandoned($q) { return $q->where('status', self::STATUS_ABANDONED); }
    public function scopeExpired($q)   { return $q->whereNotNull('expires_at')->where('expires_at','<', now()); }

    // إيجاد كارت المستخدم/الضيف الحالي
    public function scopeActiveFor($q, ?int $userId, ?string $sessionId)
    {
        return $q->active()
            ->when($userId, fn($qq)=>$qq->where('user_id', $userId))
            ->when(!$userId && $sessionId, fn($qq)=>$qq->where('session_id', $sessionId));
    }

    /* ---------- منطق الحساب ---------- */
    public function recalculateTotals(): self
    {
        // مجاميع السطور
        $row = $this->items()
            ->selectRaw('SUM(line_subtotal) as sub, SUM(line_discount) as item_disc, SUM(qty) as cnt')
            ->first();

        $subtotal     = (float) ($row->sub ?? 0.0);
        $itemCount    = (int)   ($row->cnt ?? 0);
        $itemDiscount = (float) ($row->item_disc ?? 0.0);

        $shipping = (float) $this->shipping_total;
        $tax      = (float) $this->tax_total;

        // خصم الكوبون إن وُجد
        $couponDiscount = 0.0;
        $baseForCoupon  = max(0.0, $subtotal - $itemDiscount);

        if (method_exists($this, 'coupon') && $this->coupon) {
            if (class_exists(\App\Services\CouponService::class)
                && method_exists(app(\App\Services\CouponService::class), 'calculate')) {
                // حساب عبر الخدمة (لو متاح)
                $couponDiscount = (float) app(\App\Services\CouponService::class)
                    ->calculate($this, $baseForCoupon, ['item_count' => $itemCount]);
            } elseif (method_exists($this->coupon, 'calculateDiscount')) {
                // أو مباشرة عبر موديل الكوبون
                $couponDiscount = (float) $this->coupon->calculateDiscount($baseForCoupon);
            }
        }

        $discount = $itemDiscount + $couponDiscount;
        $grand    = max(0.0, $subtotal - $discount + $shipping + $tax);

        $this->forceFill([
            'subtotal'       => $subtotal,
            'item_count'     => $itemCount,
            'discount_total' => $discount,
            'grand_total'    => $grand,
        ])->save();

        return $this;
    }

    /* ---------- حالات ---------- */
    public function markAsConverted(): self
    {
        return tap($this, function ($cart) {
            $cart->status = self::STATUS_CONVERTED;
            $cart->save();
        });
    }

    public function markAsAbandoned(): self
    {
        return tap($this, function ($cart) {
            $cart->status = self::STATUS_ABANDONED;
            $cart->save();
        });
    }

    /* ---------- هيلبرز/أكسسورات ---------- */
    public function isEmpty(): bool   { return (int) $this->item_count === 0; }
    public function isExpired(): bool { return $this->expires_at && $this->expires_at->isPast(); }
    public function getIsGuestCartAttribute(): bool { return !$this->user_id && (bool)$this->session_id; }

    public function getFormattedGrandTotalAttribute(): string
    {
        return number_format((float) $this->grand_total, 2).' '.$this->currency;
    }

    /* ---------- مُنشِئات مساعدة ---------- */
    public static function getOrCreateForUser(int $userId): self
    {
        return static::firstOrCreate(
            ['user_id' => $userId, 'status' => self::STATUS_ACTIVE],
            [
                'session_id'    => null,
                'currency'      => config('app.currency', 'USD'),
                'item_count'    => 0,
                'subtotal'      => 0,
                'discount_total'=> 0,
                'shipping_total'=> 0,
                'tax_total'     => 0,
                'grand_total'   => 0,
                'expires_at'    => now()->addDays(7),
            ]
        );
    }

    /** للضيوف */
    public static function getOrCreateForSession(string $sessionId): self
    {
        return static::firstOrCreate(
            ['session_id' => $sessionId, 'status' => self::STATUS_ACTIVE],
            [
                'user_id'       => null,
                'currency'      => config('app.currency', 'USD'),
                'item_count'    => 0,
                'subtotal'      => 0,
                'discount_total'=> 0,
                'shipping_total'=> 0,
                'tax_total'     => 0,
                'grand_total'   => 0,
                'expires_at'    => now()->addDays(7),
            ]
        );
    }
}
