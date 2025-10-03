<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

class UserController extends Controller
{
    /**
     * GET /api/users
     */
    public function index(Request $request)
    {
        $data = $request->validate([
            'q'              => ['nullable','string'],
            'email'          => ['nullable','email'],
            'is_active'      => ['nullable','boolean'],
            'role_id'        => ['nullable','integer','exists:roles,id'],
            'role'           => ['nullable'], // string | array
            'has_permission' => ['nullable','string'],
            'created_from'   => ['nullable','date'],
            'created_to'     => ['nullable','date'],
            'sort'           => ['nullable','string', Rule::in(['newest','name_asc','name_desc','email_asc','email_desc'])],
            'per_page'       => ['nullable','integer','min:1','max:100'],
        ]);

        $perPage = (int)($data['per_page'] ?? 15);

        $query = User::query()->with(['roles:id,name,slug','profile']);

        if (!empty($data['q'])) {
            $q = $data['q'];
            $query->where(function($x) use ($q) {
                $x->where('first_name','like',"%$q%")
                  ->orWhere('last_name','like',"%$q%")
                  ->orWhere('email','like',"%$q%");
            });
        }

        if (!empty($data['email'])) {
            $query->where('email', $data['email']);
        }

        if (isset($data['is_active'])) {
            $query->where('is_active', (bool)$data['is_active']);
        }

        if (!empty($data['role_id'])) {
            $query->whereHas('roles', fn($r) => $r->where('roles.id', $data['role_id']));
        }

        if (!empty($data['role'])) {
            $roles = is_array($data['role']) ? $data['role'] : [$data['role']];
            $slugs = collect($roles)->map(fn($r) => Str::slug($r, '_'))->all();

            $query->whereHas('roles', function($r) use ($roles, $slugs) {
                $r->whereIn('name', $roles)->orWhereIn('slug', $slugs);
            });
        }

        if (!empty($data['has_permission'])) {
            $permSlug = Str::slug($data['has_permission'], '_');
            $query->where(function($q) use ($permSlug) {
                $q->whereHas('roles.permissions', function($p) use ($permSlug) {
                    $p->where('permissions.slug', $permSlug);
                })->orWhereHas('directPermissions', function($p) use ($permSlug) {
                    $p->where('permissions.slug', $permSlug);
                });
            });
        }

        $from = !empty($data['created_from']) ? Carbon::parse($data['created_from'])->startOfDay() : null;
        $to   = !empty($data['created_to'])   ? Carbon::parse($data['created_to'])->endOfDay()   : null;
        if ($from && $to) $query->whereBetween('created_at', [$from, $to]);
        elseif ($from)    $query->where('created_at', '>=', $from);
        elseif ($to)      $query->where('created_at', '<=', $to);

        switch ($data['sort'] ?? 'newest') {
            case 'name_asc':  $query->orderBy('first_name')->orderBy('last_name'); break;
            case 'name_desc': $query->orderByDesc('first_name')->orderByDesc('last_name'); break;
            case 'email_asc': $query->orderBy('email','asc'); break;
            case 'email_desc':$query->orderBy('email','desc'); break;
            default:          $query->orderByDesc('created_at');
        }

        return response()->json($query->paginate($perPage));
    }

    /**
     * POST /api/users
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'first_name' => ['required','string','max:255'],
            'last_name'  => ['required','string','max:255'],
            'email'      => ['required','email','unique:users,email'],
            'password'   => ['required','string','min:8','confirmed'],
            'is_active'  => ['nullable','boolean'],

            'roles'      => ['nullable','array'],
            'roles.*'    => ['integer','exists:roles,id'],

            'profile'                => ['nullable','array'],
            'profile.phone_number'   => ['nullable','string','max:50'],
            'profile.address'        => ['nullable','string','max:255'],
            'profile.city'           => ['nullable','string','max:100'],
            'profile.country'        => ['nullable','string','max:100'],
            'profile.birthdate'      => ['nullable','date'],
            'profile.gender'         => ['nullable','string','in:male,female,other'],
            'profile.profile_image'  => ['nullable','image','mimes:jpeg,png,jpg,webp','max:4096'],
        ]);

        return DB::transaction(function () use ($request, $data) {
            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name'  => $data['last_name'],
                'email'      => $data['email'],
                'password'   => Hash::make($data['password']),
                'is_active'  => $data['is_active'] ?? true,
            ]);

            if (!empty($data['roles'])) {
                $user->roles()->sync($data['roles']);
            }

            if (!empty($data['profile'])) {
                $profileData = collect($data['profile'])->except('profile_image')->toArray();

                if (!empty($data['profile']['profile_image']) && $data['profile']['profile_image']->isValid()) {
                    $path = $data['profile']['profile_image']->store('profile_images','public');
                    $profileData['profile_image'] = $path;
                }

                $user->profile()->create($profileData);
            }

            Log::info('User created', [
                'target_user_id' => $user->id,
                'by_user_id'     => auth()->id(),
                'ip'             => $request->ip(),
                'agent'          => $request->userAgent(),
            ]);

            return response()->json($user->load('roles','profile'), 201);
        });
    }

    /**
     * GET /api/users/{user}
     */
    public function show(User $user)
    {
        return response()->json($user->load('roles','profile'));
    }

    /**
     * PUT /api/users/{user}
     */
    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'first_name' => ['sometimes','string','max:255'],
            'last_name'  => ['sometimes','string','max:255'],
            'email'      => ['sometimes','email', Rule::unique('users','email')->ignore($user->id)],
            'password'   => ['sometimes','nullable','string','min:8','confirmed'],
            'is_active'  => ['sometimes','boolean'],

            'roles'      => ['sometimes','array'],
            'roles.*'    => ['integer','exists:roles,id'],

            'profile'                => ['sometimes','array'],
            'profile.phone_number'   => ['nullable','string','max:50'],
            'profile.address'        => ['nullable','string','max:255'],
            'profile.city'           => ['nullable','string','max:100'],
            'profile.country'        => ['nullable','string','max:100'],
            'profile.birthdate'      => ['nullable','date'],
            'profile.gender'         => ['nullable','string','in:male,female,other'],
            'profile.profile_image'  => ['nullable','image','mimes:jpeg,png,jpg,webp','max:4096'],
            'profile.remove_image'   => ['nullable','boolean'],
        ]);

        return DB::transaction(function () use ($request, $data, $user) {
            $update = collect($data)->only(['first_name','last_name','email','is_active'])->toArray();

            if (array_key_exists('password', $data) && !empty($data['password'])) {
                $update['password'] = Hash::make($data['password']);
            }

            if (!empty($update)) {
                $user->update($update);
            }

            if (array_key_exists('roles', $data)) {
                $user->roles()->sync($data['roles'] ?? []);
            }

            if (array_key_exists('profile', $data)) {
                $p = $data['profile'];
                $profile = $user->profile()->firstOrCreate(['user_id' => $user->id]);

                if (!empty($p['remove_image']) && $profile->profile_image) {
                    if (Storage::disk('public')->exists($profile->profile_image)) {
                        Storage::disk('public')->delete($profile->profile_image);
                    }
                    $profile->profile_image = null;
                }

                if (!empty($p['profile_image']) && $p['profile_image']->isValid()) {
                    if ($profile->profile_image && Storage::disk('public')->exists($profile->profile_image)) {
                        Storage::disk('public')->delete($profile->profile_image);
                    }
                    $path = $p['profile_image']->store('profile_images','public');
                    $profile->profile_image = $path;
                }

                $profile->fill(collect($p)->except(['profile_image','remove_image'])->toArray());
                $profile->save();
            }

            Log::info('User updated', [
                'target_user_id' => $user->id,
                'by_user_id'     => auth()->id(),
                'ip'             => $request->ip(),
            ]);

            return response()->json($user->load('roles','profile'));
        });
    }

    /**
     * DELETE /api/users/{user}
     */
    public function destroy(User $user)
    {
        $id = $user->id;
        $user->delete();

        Log::warning('User deleted', [
            'target_user_id' => $id,
            'by_user_id'     => auth()->id(),
        ]);

        return response()->json(null, 204);
    }

    /**
     * POST /api/users/{user}/roles
     */
    public function assignRole(Request $request, User $user)
    {
        $data = $request->validate([
            'roles'   => ['required','array'],
            'roles.*' => ['integer','exists:roles,id'],
        ]);

        $user->roles()->sync($data['roles']);

        Log::info('Roles synced', [
            'target_user_id' => $user->id,
            'by_user_id'     => auth()->id(),
            'roles'          => $data['roles'],
        ]);

        return response()->json($user->load('roles'));
    }

    /**
     * POST /api/users/{user}/permissions
     */
    public function assignDirectPermissions(Request $request, User $user)
    {
        $data = $request->validate([
            'permissions'   => ['required','array'],
            'permissions.*' => ['integer','exists:permissions,id'],
        ]);

        $user->directPermissions()->sync($data['permissions']);

        Log::info('Direct permissions synced', [
            'target_user_id' => $user->id,
            'by_user_id'     => auth()->id(),
            'permissions'    => $data['permissions'],
        ]);

        return response()->json([
            'user_id'     => $user->id,
            'permissions' => $user->directPermissions()->select('permissions.id','permissions.name','permissions.slug')->get(),
        ]);
    }
}
