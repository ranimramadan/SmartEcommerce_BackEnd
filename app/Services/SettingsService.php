<?php
namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingsService
{
    /** يدعم group.key ويستخدم كاش داخلي */
    public function get(string $fullKey, $default = null)
    {
        return Cache::remember("setting:$fullKey", 600, fn() => Setting::v($fullKey, $default));
    }

    public function set(string $fullKey, $value, ?string $group = null, bool $public = false): void
    {
        // لو مرّ group separatley نخيطه
        if ($group && !str_contains($fullKey, '.')) {
            $fullKey = "{$group}.{$fullKey}";
        }
        Setting::put($fullKey, $value, $public);
        Cache::forget("setting:$fullKey");
        Cache::forget('settings:public_map');
    }

    /** قائمة الإعدادات (كلّها أو مجموعة محدّدة) */
    public function list(?string $group = null): array
    {
        if ($group) {
            return Setting::getByGroup($group);
        }

        // رجّع map group.key => value
        return Setting::query()->get()
            ->mapWithKeys(fn($s)=>["{$s->group}.{$s->key}" => $s->value])
            ->toArray();
    }

    /** upsert متعدد */
    public function upsertMany(array $rows): void
    {
        foreach ($rows as $row) {
            $key   = (string)($row['key'] ?? '');
            $value = $row['value'] ?? null;
            $group = (string)($row['group'] ?? 'general');

            if ($key === '') continue;

            $this->set("{$group}.{$key}", $value, null, false);
        }
        Cache::forget('settings:public_map');
    }
}
