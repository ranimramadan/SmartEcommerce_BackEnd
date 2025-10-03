<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $fillable = [
        'code',
        'type',                 // percent | amount | free_shipping
        'value',                // percent: 0..100 | amount: رقم | free_shipping: null/0
        'max_discount',
        'min_cart_total',
        'min_items_count',
        'max_uses',             // إجمالي
        'max_uses_per_user',    // لكل مستخدم
        'start_at',
        'end_at',
        'is_active',
    ];

    protected $casts = [
        'value'               => 'decimal:2',
        'max_discount'        => 'decimal:2',
        'min_cart_total'      => 'decimal:2',
        'min_items_count'     => 'integer',
        'max_uses'            => 'integer',
        'max_uses_per_user'   => 'integer',
        'start_at'            => 'datetime',
        'end_at'              => 'datetime',
        'is_active'           => 'boolean',
    ];

    // Types
    public const TYPE_PERCENT       = 'percent';
    public const TYPE_AMOUNT        = 'amount';
    public const TYPE_FREE_SHIPPING = 'free_shipping';

    /* ---------------- علاقات ---------------- */
    public function carts()  { return $this->hasMany(Cart::class); }
    public function orders() { return $this->hasMany(Order::class); }

    // لو عندك جدول الاستردادات
    public function redemptions()
    {
        return $this->hasMany(CouponRedemption::class);
    }

    /* ---------------- سكوبات ---------------- */
    public function scopeActive($q)
    {
        return $q->where('is_active', true)
            ->where(function ($w) {
                $w->whereNull('start_at')->orWhere('start_at', '<=', now());
            })
            ->where(function ($w) {
                $w->whereNull('end_at')->orWhere('end_at', '>=', now());
            });
    }

    public function scopeByCode($q, string $code)
    {
        return $q->where('code', strtoupper(trim($code)));
    }

    /* ---------------- هيلبرز أساسية ---------------- */
    public function isFreeShipping(): bool
    {
        return $this->type === self::TYPE_FREE_SHIPPING;
    }

    public function calculateDiscount(float $subtotal): float
    {
        if ($subtotal <= 0) return 0.0;

        switch ($this->type) {
            case self::TYPE_PERCENT:
                $pct = max(0.0, min(100.0, (float) $this->value));
                $discount = $subtotal * ($pct / 100.0);
                if ($this->max_discount !== null) {
                    $discount = min($discount, (float) $this->max_discount);
                }
                return round($discount, 2);

            case self::TYPE_AMOUNT:
                $val = max(0.0, (float) $this->value);
                return round(min($val, $subtotal), 2);

            case self::TYPE_FREE_SHIPPING:
                return 0.0; // يُطبَّق على الشحن خارج هذا الحساب
        }

        return 0.0;
    }

    /**
     * صلاحية عامة (تفعيل + نافذة زمنية + حدود عامة)
     * ملاحظة: حدود الاستخدام الدقيقة نفحصها بوظائف أدناه.
     */
    public function isCurrentlyActive(): bool
    {
        if (!$this->is_active) return false;
        if ($this->start_at && $this->start_at->isFuture()) return false;
        if ($this->end_at   && $this->end_at->isPast())    return false;
        return true;
    }

    /**
     * صالح للسلة (قيمة دنيا/عدد عناصر …) + التحقق الزمني.
     * ملاحظة: لا تفحص هنا عدد الاستخدام — نخليه بدالة مستقلة
     * لأنك أحيانًا تحتاجي user_id (لحد لكل مستخدم).
     */
    public function isValidForCart(Cart $cart): bool
    {
        if (!$this->isCurrentlyActive()) return false;

        if ($this->min_cart_total !== null && (float)$cart->subtotal < (float)$this->min_cart_total) {
            return false;
        }
        if ($this->min_items_count !== null && (int)$cart->item_count < (int)$this->min_items_count) {
            return false;
        }

        return true;
    }

    /**
     * حساب استخدام الكوبون إجمالاً ومن قِبَل مستخدم معيّن.
     * الأفضل تعدّي إنك تحسبي الاستردادات المؤكدة فقط.
     * لو ما بدك redemptions: بدّليها بطلبات مدفوعة فقط.
     */
    public function usageCount(?int $userId = null): int
    {
        if (method_exists($this, 'redemptions')) {
            $q = $this->redemptions()->whereNotNull('order_id');
            // لو بدك بس المدفوع: انضمي لجدول orders وتفلتر paid
            // $q->whereHas('order', fn($o)=>$o->where('payment_status','paid'));
            if ($userId) $q->where('user_id', $userId);
            return (int) $q->count();
        }

        // fallback: من الطلبات المدفوعة
        $q = $this->orders()->where('payment_status', 'paid');
        if ($userId) $q->where('user_id', $userId);
        return (int) $q->count();
    }

    public function remainingUses(?int $userId = null): array
    {
        $totalLeft = null;
        $userLeft  = null;

        if ($this->max_uses !== null) {
            $used = $this->usageCount(null);
            $totalLeft = max(0, (int)$this->max_uses - $used);
        }

        if ($this->max_uses_per_user !== null && $userId) {
            $usedByUser = $this->usageCount($userId);
            $userLeft = max(0, (int)$this->max_uses_per_user - $usedByUser);
        }

        return ['total_left' => $totalLeft, 'user_left' => $userLeft];
    }

    /**
     * فحص نهائي قبل التطبيق (يشمل حدود الاستخدام)
     */
    public function canApplyToCart(Cart $cart, ?int $userId = null): bool
    {
        if (!$this->isValidForCart($cart)) return false;

        // حدود إجمالية
        if ($this->max_uses !== null) {
            if ($this->remainingUses(null)['total_left'] === 0) return false;
        }

        // حدود لكل مستخدم
        if ($this->max_uses_per_user !== null && $userId) {
            if ($this->remainingUses($userId)['user_left'] === 0) return false;
        }

        return true;
    }

    /* ---------------- Mutators ---------------- */
    public function setCodeAttribute($val): void
    {
        $this->attributes['code'] = strtoupper(trim((string)$val));
    }

    protected static function booted()
    {
        // حراسة القيم
        static::saving(function (Coupon $c) {
            if ($c->type === self::TYPE_PERCENT) {
                $c->value = max(0.0, min(100.0, (float)$c->value));
            }
            if ($c->type === self::TYPE_AMOUNT) {
                $c->value = max(0.0, (float)$c->value);
            }
            if ($c->type === self::TYPE_FREE_SHIPPING) {
                $c->value = null; // ماله لزوم
            }
        });
    }

    /* ---------------- Shortcuts ---------------- */
    public static function findByCode(string $code): ?self
    {
        return static::byCode($code)->active()->first();
    }
}
