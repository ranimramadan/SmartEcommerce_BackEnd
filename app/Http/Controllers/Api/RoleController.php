<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Facades\Log;

class RoleController extends Controller
{
    /**
     * GET /api/roles
     * q, per_page, sort=name_asc|name_desc
     * (مضاف Cache بوسم roles)
     */
    public function index(Request $request)
    {
        $data = $request->validate([
            'q'        => ['nullable','string'],
            'per_page' => ['nullable','integer','min:1','max:100'],
            'sort'     => ['nullable','string', Rule::in(['name_asc','name_desc'])],
            'page'     => ['nullable','integer','min:1'],
        ]);

        $perPage = (int)($data['per_page'] ?? 50);

        $query = Role::query()->withCount(['users','permissions']);

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

        $key = 'roles:index:'.md5(json_encode([
            'q' => $data['q'] ?? null,
            'per_page' => $perPage,
            'sort' => $data['sort'] ?? 'name_asc',
            'page' => $data['page'] ?? (int)($request->get('page', 1)),
        ]));

        $ttl = 300; // 5 دقائق
        $taggable = Cache::getStore() instanceof TaggableStore;

        $payload = $taggable
            ? Cache::tags(['roles'])->remember($key, $ttl, fn() => $query->paginate($perPage)->toArray())
            : Cache::remember($key, $ttl, fn() => $query->paginate($perPage)->toArray());

        return response()->json($payload);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => ['required','string','max:255','unique:roles,name'],
            'slug'        => ['nullable','string','max:255','unique:roles,slug'],
            'description' => ['nullable','string'],
        ]);

        $data['slug'] = $data['slug'] ?: Str::slug($data['name'], '_');

        $role = Role::create($data);

        // Flush cache
        if (Cache::getStore() instanceof TaggableStore) {
            Cache::tags(['roles'])->flush();
        }

        Log::info('Role created', [
            'role_id'   => $role->id,
            'by_user_id'=> auth()->id(),
        ]);

        return response()->json($role, 201);
    }

    public function show(Role $role)
    {
        return response()->json($role->load('permissions:id,name,slug'));
    }

    public function update(Request $request, Role $role)
    {
        $data = $request->validate([
            'name'        => ['sometimes','string','max:255', Rule::unique('roles','name')->ignore($role->id)],
            'slug'        => ['sometimes','nullable','string','max:255', Rule::unique('roles','slug')->ignore($role->id)],
            'description' => ['nullable','string'],
        ]);

        if (isset($data['name']) && empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name'], '_');
        }

        $role->update($data);

        if (Cache::getStore() instanceof TaggableStore) {
            Cache::tags(['roles'])->flush();
        }

        Log::info('Role updated', [
            'role_id'   => $role->id,
            'by_user_id'=> auth()->id(),
        ]);

        return response()->json($role);
    }

    public function destroy(Role $role)
    {
        // لتفادي مشاكل قيود المفاتيح (إن لم تكن cascadeOnDelete)
        $role->permissions()->detach();
        $role->users()->detach();

        $id = $role->id;
        $role->delete();

        if (Cache::getStore() instanceof TaggableStore) {
            Cache::tags(['roles'])->flush();
        }

        Log::warning('Role deleted', [
            'role_id'   => $id,
            'by_user_id'=> auth()->id(),
        ]);

        return response()->json(null, 204);
    }

    /**
     * POST /api/roles/{role}/permissions
     * body: { "permissions": [1,2,5] }
     */
    public function assignPermissions(Request $request, Role $role)
    {
        $data = $request->validate([
            'permissions'   => ['required','array'],
            'permissions.*' => ['integer','exists:permissions,id'],
        ]);

        $role->permissions()->sync($data['permissions']);

        if (Cache::getStore() instanceof TaggableStore) {
            Cache::tags(['roles'])->flush();
        }

        Log::info('Role permissions synced', [
            'role_id'       => $role->id,
            'by_user_id'    => auth()->id(),
            'permissions'   => $data['permissions'],
        ]);

        return response()->json($role->load('permissions:id,name,slug'));
    }
}
