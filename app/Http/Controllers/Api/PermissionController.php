<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Facades\Log;

class PermissionController extends Controller
{
    /**
     * GET /api/permissions
     * q, per_page, sort=name_asc|name_desc
     * (مضاف Cache بوسم permissions)
     */
    public function index(Request $request)
    {
        $data = $request->validate([
            'q'        => ['nullable','string'],
            'per_page' => ['nullable','integer','min:1','max:100'],
            'sort'     => ['nullable','string', Rule::in(['name_asc','name_desc'])],
            'page'     => ['nullable','integer','min:1'],
        ]);

        $perPage = (int)($data['per_page'] ?? 100);

        $query = Permission::query()->withCount('roles');

        if (!empty($data['q'])) {
            $q = $data['q'];
            $query->where(function($x) use ($q) {
                $x->where('name','like',"%$q%")
                  ->orWhere('slug','like',"%$q%");
            });
        }

        switch ($data['sort'] ?? 'name_asc') {
            case 'name_desc':
                $query->orderBy('name','desc');
                break;
            default:
                $query->orderBy('name','asc');
        }

        $key = 'permissions:index:'.md5(json_encode([
            'q' => $data['q'] ?? null,
            'per_page' => $perPage,
            'sort' => $data['sort'] ?? 'name_asc',
            'page' => $data['page'] ?? (int)($request->get('page', 1)),
        ]));

        $ttl = 300; // 5 دقائق
        $taggable = Cache::getStore() instanceof TaggableStore;

        $payload = $taggable
            ? Cache::tags(['permissions'])->remember($key, $ttl, fn() => $query->paginate($perPage)->toArray())
            : Cache::remember($key, $ttl, fn() => $query->paginate($perPage)->toArray());

        return response()->json($payload);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => ['required','string','max:255','unique:permissions,name'],
            'slug'        => ['nullable','string','max:255','unique:permissions,slug'],
            'description' => ['nullable','string'],
        ]);

        // توحيد الـ slug بصيغة snake_case
        $data['slug'] = $data['slug'] ?: Str::of($data['name'])->lower()->replace(' ', '_')->toString();

        $perm = Permission::create($data);

        if (Cache::getStore() instanceof TaggableStore) {
            Cache::tags(['permissions','roles'])->flush();
        }

        Log::info('Permission created', [
            'permission_id' => $perm->id,
            'by_user_id'    => auth()->id(),
        ]);

        return response()->json($perm, 201);
    }

    public function show(Permission $permission)
    {
        return response()->json($permission->load('roles:id,name,slug'));
    }

    public function update(Request $request, Permission $permission)
    {
        $data = $request->validate([
            'name'        => ['sometimes','string','max:255', Rule::unique('permissions','name')->ignore($permission->id)],
            'slug'        => ['sometimes','nullable','string','max:255', Rule::unique('permissions','slug')->ignore($permission->id)],
            'description' => ['nullable','string'],
        ]);

        if (isset($data['name']) && empty($data['slug'])) {
            $data['slug'] = Str::of($data['name'])->lower()->replace(' ', '_')->toString();
        }

        $permission->update($data);

        if (Cache::getStore() instanceof TaggableStore) {
            Cache::tags(['permissions','roles'])->flush();
        }

        Log::info('Permission updated', [
            'permission_id' => $permission->id,
            'by_user_id'    => auth()->id(),
        ]);

        return response()->json($permission);
    }

    public function destroy(Permission $permission)
    {
        $id = $permission->id;

        $permission->roles()->detach(); // لتفادي قيود FK إن لم تكن onDelete cascade
        $permission->delete();

        if (Cache::getStore() instanceof TaggableStore) {
            Cache::tags(['permissions','roles'])->flush();
        }

        Log::warning('Permission deleted', [
            'permission_id' => $id,
            'by_user_id'    => auth()->id(),
        ]);

        return response()->json(null, 204);
    }
}
