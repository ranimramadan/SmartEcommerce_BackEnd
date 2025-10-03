<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = [
        'group',
        'key',
        'value',
        'is_public',
    ];

    protected $casts = [
        // خليه array/collection حسب تفضيلك. array ممتاز لمعظم الحالات.
        'value'     => 'array',
        'is_public' => 'boolean',
    ];

    // مجموعات شائعة
    public const GROUP_GENERAL = 'general';
    public const GROUP_PAYMENT = 'payment';
    public const GROUP_UI      = 'ui';
    public const GROUP_SHIPPING= 'shipping';
    public const GROUP_I18N    = 'i18n';

    /* ===================== Scopes ===================== */
    public function scopePublic($q)   { return $q->where('is_public', true); }
    public function scopeByGroup($q, string $group) { return $q->where('group', $group); }

    /* ===================== Helpers (جديدة ومُنظمة) ===================== */

    /**
     * احصلي على قيمة إعداد بصيغة "group.key"
     * مثال: Setting::v('general.site_name', 'My Shop')
     */
    public static function v(string $fullKey, $default = null)
    {
        [$group, $key] = self::splitFullKey($fullKey);

        $cacheKey = "setting:$group.$key";

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($group, $key, $default) {
            $row = self::query()->where('group', $group)->where('key', $key)->first();
            return $row ? $row->value : $default;
        });
    }

    /**
     * ضعي/حدّثي قيمة إعداد بصيغة "group.key"
     * مثال: Setting::put('payment.supported_methods', ['stripe','cod'], true)
     */
    public static function put(string $fullKey, $value, bool $isPublic = false): self
    {
        [$group, $key] = self::splitFullKey($fullKey);

        $row = self::updateOrCreate(
            ['group' => $group, 'key' => $key],
            ['value' => $value, 'is_public' => $isPublic]
        );

        // نظّفي الكاش للمفتاح المحدد + خريطة العلن
        Cache::forget("setting:$group.$key");
        Cache::forget("settings:public_map");

        return $row;
    }

    /**
     * احصلي على خريطة كل الإعدادات العامة (للواجهة) كمصفوفة "group.key" => value
     */
    public static function publicMap(): array
    {
        return Cache::remember('settings:public_map', now()->addMinutes(10), function () {
            return self::query()->public()->get()
                ->mapWithKeys(fn($s) => [ "{$s->group}.{$s->key}" => $s->value ])
                ->toArray();
        });
    }

    /** توافق خلفي: إن مرّوا key بدون group، نفترض group=general */
    public static function get($key, $default = null)
    {
        // إن كان فيه نقطة، نتعامل معه كـ group.key
        if (str_contains($key, '.')) {
            return self::v($key, $default);
        }

        // توافقي: group=general
        return self::v(self::GROUP_GENERAL . '.' . $key, $default);
    }

    /** توافقي: set($key,$value,$group='general', $isPublic=false) */
    public static function set($key, $value, $group = self::GROUP_GENERAL, $isPublic = false)
    {
        return self::put("$group.$key", $value, $isPublic);
    }

    /** جلب مجموعة كاملة كمصفوفة key=>value (بدون group prefix) */
    public static function getByGroup(string $group): array
    {
        return self::query()->where('group', $group)->get()
            ->pluck('value', 'key')->toArray();
    }

    public static function getPublic(): array
    {
        // أبقيتها للِّي متعوّدين عليها—لكن يفضَّل استخدام publicMap()
        return self::publicMap();
    }

    /* ===================== Utilities ===================== */

    public function getFullKeyAttribute(): string
    {
        return "{$this->group}.{$this->key}";
    }

    protected static function splitFullKey(string $fullKey): array
    {
        if (!str_contains($fullKey, '.')) {
            // لو ما فيها نقطة، نفترض general.key
            return [self::GROUP_GENERAL, $fullKey];
        }
        [$group, $key] = explode('.', $fullKey, 2);
        return [$group, $key];
    }

    /* ===================== Cache Invalidation ===================== */
    protected static function booted()
    {
        static::saved(function (self $row) {
            Cache::forget("setting:{$row->group}.{$row->key}");
            Cache::forget('settings:public_map');
        });

        static::deleted(function (self $row) {
            Cache::forget("setting:{$row->group}.{$row->key}");
            Cache::forget('settings:public_map');
        });
    }
}
