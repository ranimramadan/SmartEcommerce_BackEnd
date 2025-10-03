<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentProvider extends Model
{
    // الحقول القابلة للتعبئة
    protected $fillable = [
        'code',
        'name',
        'type',              // online | offline
        'config',            // JSON لمفاتيح المزود (secret/public/test_mode..)
        'is_active',
        'sort_order',
    ];

    // التحويلات
    protected $casts = [
        'config'     => 'array',
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    // ثوابت الأنواع والأكواد
    public const TYPE_ONLINE  = 'online';
    public const TYPE_OFFLINE = 'offline';

    public const CODE_STRIPE  = 'stripe';
    public const CODE_PAYPAL  = 'paypal';
    public const CODE_COD     = 'cod';

    /* --------------------------- العلاقات --------------------------- */

    public function orders()
    {
        // orders.payment_provider_id
        return $this->hasMany(Order::class);
    }

    public function payments()
    {
        // payments.payment_provider_id
        return $this->hasMany(Payment::class);
    }

    /* --------------------------- سكوبات مفيدة --------------------------- */

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeOnline($q)
    {
        return $q->where('type', self::TYPE_ONLINE);
    }

    public function scopeOffline($q)
    {
        return $q->where('type', self::TYPE_OFFLINE);
    }

    public function scopeOrdered($q)
    {
        // للعرض في الداشبورد/الواجهة
        return $q->orderBy('sort_order')->orderBy('name');
    }

    /** سكوب جاهز لواجهة الـCheckout: مزودات مفعّلة ومرتبة */
    public function scopeForCheckout($q)
    {
        return $q->active()->ordered();
    }

    /* --------------------------- Mutators/Accessors --------------------------- */

    /** توحيد الكود دائمًا lowercase بدون فراغات */
    public function setCodeAttribute($value)
    {
        $this->attributes['code'] = strtolower(trim((string) $value));
    }

    /** هل في وضع الاختبار؟ تُقرأ من config.test_mode */
    public function getIsTestModeAttribute()
    {
        return (bool) $this->getConfigValue('test_mode', false);
    }

    /**
     * عداد الدفعات الناجحة
     * ملاحظة: حالاتك هي authorized|captured|refunded|failed
     * غالبًا النجاح الحقيقي = captured (تم التحصيل)
     */
    public function getSuccessfulPaymentsCountAttribute()
    {
        return $this->payments()
            ->where('status', 'captured') // أو whereIn(['authorized','captured']) حسب منطقك
            ->count();
    }

    /* --------------------------- مساعدات --------------------------- */

    public function isOnline(): bool
    {
        return $this->type === self::TYPE_ONLINE;
    }

    public function isOffline(): bool
    {
        return $this->type === self::TYPE_OFFLINE;
    }

    public function isCOD(): bool
    {
        return $this->code === self::CODE_COD;
    }

    /** قلب حالة التفعيل */
    public function toggle(): self
    {
        $this->is_active = ! $this->is_active;
        $this->save();

        return $this;
    }

    /** قراءة قيمة من JSON config بسهولة */
    public function getConfigValue(string $key, $default = null)
    {
        return data_get($this->config ?? [], $key, $default);
    }

    /** ضبط قيمة في JSON config وحفظها في الذاكرة (لا تحفظ مباشرة) */
    public function setConfigValue(string $key, $value): self
    {
        $cfg = $this->config ?? [];
        data_set($cfg, $key, $value);
        $this->config = $cfg;

        return $this;
    }
}
