<?php
// app/Http/Controllers/Api/AuthController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'first_name' => ['required','string','max:255'],
            'last_name'  => ['required','string','max:255'],
            'email'      => ['required','email','unique:users,email'],
            'password'   => ['required','string','min:8','confirmed'],
            'profile'    => ['nullable','array'],
        ]);

        try {
            $user = DB::transaction(function () use ($data) {
                $user = User::create([
                    'first_name' => $data['first_name'],
                    'last_name'  => $data['last_name'],
                    'email'      => $data['email'],
                    'password'   => Hash::make($data['password']),
                    'is_active'  => true,
                ]);

                if (!empty($data['profile'])) {
                    $user->profile()->create($data['profile']);
                }

                return $user;
            });

            // مافي load('roles') حالياً لتفادي أي مشاكل علاقات
            return response()->json(['user' => $user->load('profile')], 201);

        } catch (\Throwable $e) {
            // نسجل رسالة الخطأ + الستاك
            Log::error('Register failed', [
                'msg' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            // نرجّع رسالة واضحة خلال التطوير (بدون فضح تفاصيل في الإنتاج)
            if (config('app.env') !== 'production') {
                return response()->json([
                    'success' => false,
                    'message' => 'Register failed: '.$e->getMessage(),
                ], 500);
            }

            return response()->json([
                'success' => false,
                'message' => 'Server Error',
            ], 500);
        }
    }

    // ... login/logout/me بدون تغيير
}


// namespace App\Http\Controllers\Api;

// use App\Http\Controllers\Controller;
// use Illuminate\Http\Request;
// use Illuminate\Support\Facades\Auth;
// use Illuminate\Support\Facades\Hash;
// use App\Models\User;

// class AuthController extends Controller
// {
//     public function register(Request $request)
//     {
//         $data = $request->validate([
//             'first_name' => ['required','string','max:255'],
//             'last_name'  => ['required','string','max:255'],
//             'email'      => ['required','email','unique:users,email'],
//             'password'   => ['required','string','min:8','confirmed'],
//             'profile'    => ['nullable','array'],
//         ]);

//         $user = User::create([
//             'first_name' => $data['first_name'],
//             'last_name'  => $data['last_name'],
//             'email'      => $data['email'],
//             'password'   => Hash::make($data['password']),
//             'is_active'  => true,
//         ]);

//         if (!empty($data['profile'])) {
//             $user->profile()->create($data['profile']);
//         }

       
//         return response()->json(
//             $user->loadMissing('profile'), 
//             201
//         );
//     }

//     public function login(Request $request)
//     {
//         $cred = $request->validate([
//             'email'    => ['required','email'],
//             'password' => ['required','string'],
//         ]);

//         if (!Auth::attempt($cred, true)) {
//             return response()->json(['message' => 'Invalid credentials'], 422);
//         }

//         $request->session()->regenerate();

//         // يحمل العلاقات إن وُجدت (ما بينكسر لو ما في roles)
//         return response()->json(
//             $request->user()->loadMissing('profile','roles')
//         );
//     }

//     public function logout(Request $request)
//     {
//         Auth::guard('web')->logout();
//         $request->session()->invalidate();
//         $request->session()->regenerateToken();

//         return response()->json(['message' => 'Logged out']);
//     }

//     public function me(Request $request)
//     {
//         return response()->json($request->user()->loadMissing('profile','roles'));
//     }
// }
