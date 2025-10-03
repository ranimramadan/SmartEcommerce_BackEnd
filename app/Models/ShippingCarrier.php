<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ShippingCarrier extends Model
{
    protected $fillable = [
        'code', 'name', 'website', 'phone', 'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ثوابت اختيارية
    public const CARRIER_INTERNAL = 'internal_fleet';
    public const CARRIER_DHL      = 'dhl';
    public const CARRIER_ARAMEX   = 'aramex';

    // علاقات
    public function shipments()
    {
        return $this->hasMany(Shipment::class);
    }

    // Scopes
    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeByCode($q, string $code)
    {
        return $q->where('code', strtolower($code));
    }

    // Normalization: نخلي code دايمًا lowercase + snake (أماناً)
    public function setCodeAttribute($value)
    {
        $this->attributes['code'] = Str::snake(strtolower($value));
    }

    // يفضّل نقرأ قالب الرابط من config بدلاً من switch
    public function getTrackingUrl(?string $trackingNumber): ?string
    {
        if (!$trackingNumber) return null;

        // ابني config/shipping.php فيها مصفوفة قوالب
        $template = config("shipping.tracking_urls.{$this->code}");
        return $template ? str_replace('{tracking}', $trackingNumber, $template) : null;
    }

    public function isInternal(): bool
    {
        return $this->code === self::CARRIER_INTERNAL;
    }

    public function toggle(): self
    {
        $this->is_active = ! $this->is_active;
        $this->save();
        return $this;
    }

    // ملاحظة مهمة: هذه الخصائص بتعمل استعلام عند كل نداء
    // استخدمي withCount بدلها لما تحتاجي العد بسرعة.
    public function getShipmentsCountAttribute(): int
    {
        return $this->shipments()->count();
    }

    public function getDeliveredShipmentsCountAttribute(): int
    {
        return $this->shipments()->where('status', 'delivered')->count();
    }
}
