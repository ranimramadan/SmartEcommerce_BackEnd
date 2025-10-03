<?php

namespace App\Models\Concerns;

use App\Models\Role;
use App\Models\Permission;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;

/**
 * Trait لفحص الأدوار والصلاحيات.
 * يعتمد على: roles, permissions, role_user, permission_role
 * و (اختياري) permission_user للصلاحيات المباشرة.
 */
trait HasRolesPermissions
{
    /** كاش مؤقت خلال الطلب لتقليل عدد الاستعلامات */
    protected ?Collection $cachedPermissionSlugs = null;

    /* ================= العلاقات ================= */

    /** user ⇄ roles (Pivot: role_user) */
    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    /** user ⇄ permissions المباشرة (Pivot: permission_user) — اختياري */
    public function directPermissions()
    {
        return $this->belongsToMany(Permission::class, 'permission_user');
    }

    /* ============== أدوات داخلية مساعدة ============== */

    /** توحيد تنسيق النص إلى slug بأسلوب lower_underscore */
    protected function normalize(string $text): string
    {
        return Str::of($text)->lower()->replace(' ', '_')->toString();
    }

    /* ============== تجميع الصلاحيات ============== */

    /** صلاحيات الأدوار كـ slugs */
    public function rolePermissionSlugs(): Collection
    {
        $roleIds = $this->roles()->pluck('roles.id');
        if ($roleIds->isEmpty()) {
            return collect();
        }

        // نجلب slugs مباشرة عبر join لتفادي تحميل موديلات كثيرة
        return Permission::query()
            ->select('permissions.slug')
            ->join('permission_role', 'permission_role.permission_id', '=', 'permissions.id')
            ->whereIn('permission_role.role_id', $roleIds)
            ->pluck('permissions.slug');
    }

    /** (اختياري) صلاحيات مباشرة للمستخدم من permission_user */
    public function directPermissionSlugs(): Collection
    {
        // لو ما في جدول/علاقة، هتكون آمنة (ترجع مجموعة فاضية)
        try {
            return $this->directPermissions()->pluck('permissions.slug');
        } catch (\Throwable $e) {
            return collect();
        }
    }

    /**
     * جميع الصلاحيات (slugs) = صلاحيات الأدوار ∪ المباشرة (مع توحيد التنسيق)
     * نتيجتها تُخزّن مؤقتًا خلال الطلب لتخفيف الاستعلامات المتكررة.
     */
    public function allPermissionSlugs(): Collection
    {
        if ($this->cachedPermissionSlugs !== null) {
            return $this->cachedPermissionSlugs;
        }

        $merged = collect($this->rolePermissionSlugs())
            ->merge($this->directPermissionSlugs())
            ->filter() // استبعاد null لو وجد
            ->map(fn ($s) => $this->normalize($s))
            ->unique()
            ->values();

        // خزّن مؤقتًا لهذا الطلب فقط
        $this->cachedPermissionSlugs = $merged;

        return $merged;
    }

    /* ============== فحوصات مساعدة ============== */

    /** هل لدى المستخدم هذا الدور (بالاسم أو الـslug)؟ */
    public function hasRole(string $roleName): bool
    {
        $slug = Str::slug($roleName, '_');
        return $this->roles()->where('name', $roleName)->exists()
            || $this->roles()->where('slug', $slug)->exists();
    }

    /** هل لدى المستخدم أي دور من هذه القائمة؟ */
    public function hasAnyRole(array $roles): bool
    {
        if (empty($roles)) return false;

        $slugs = collect($roles)->map(fn($r) => Str::slug($r, '_'))->all();

        return $this->roles()->whereIn('name', $roles)->exists()
            || $this->roles()->whereIn('slug', $slugs)->exists();
    }

    /** هل يمتلك صلاحية معيّنة (ندعم "edit product" و"edit_product")؟ */
    public function hasPermission(string $permission): bool
    {
        return $this->allPermissionSlugs()->contains($this->normalize($permission));
    }

    /** هل يمتلك أي صلاحية من القائمة؟ */
    public function hasAnyPermission(array $permissions): bool
    {
        if (empty($permissions)) return false;

        $needles = collect($permissions)->map(fn($p) => $this->normalize($p));
        $all = $this->allPermissionSlugs();

        foreach ($needles as $n) {
            if ($all->contains($n)) return true;
        }
        return false;
    }
    
// public function forgetCachedPermissions(): void
// {
//     $this->cachedPermissionSlugs = null;
// }

}
