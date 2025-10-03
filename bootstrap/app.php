<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Providers\PaymentServiceProvider;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__ . '/../routes/api.php',
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // ✅ تفعيل نمط الـ SPA stateful (الطريقة الجديدة في Laravel 12 بدل الميدلوير القديم)
        $middleware->statefulApi();

        // (اختياري) إضافة Route Model Binding ضمن مجموعة الـ API
        $middleware->appendToGroup('api', \Illuminate\Routing\Middleware\SubstituteBindings::class);

        // ✅ aliases الشائعة — ملاحظة مهمة:
        // لا تعمل alias لـ "auth:sanctum"؛ هذا يُستخدم كـ parameter لميدلوير auth داخل الراوت.
        $middleware->alias([
            'auth'       => \App\Http\Middleware\Authenticate::class,
            'permission' => \App\Http\Middleware\PermissionMiddleware::class,
            'role'       => \App\Http\Middleware\RoleMiddleware::class,
            'active'     => \App\Http\Middleware\ActiveUserMiddleware::class,

            // (اختياري) لو تستخدم alias 'bindings' في الراوت
            'bindings'   => \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // استجابة JSON موحّدة لمسارات الـ API فقط
        $exceptions->render(function (\Throwable $e, $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $payload = fn ($status, $message, $errors = null) => response()->json([
                'success' => false,
                'message' => $message,
                'errors'  => $errors,
            ], $status);

            if ($e instanceof \Illuminate\Validation\ValidationException) {
                return $payload(422, 'Validation failed', $e->errors());
            }

            if ($e instanceof \Illuminate\Auth\AuthenticationException) {
                return $payload(401, $e->getMessage() ?: 'Unauthenticated');
            }

            // Authorization/AccessDenied
            if ($e instanceof \Illuminate\Auth\Access\AuthorizationException
                || $e instanceof \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException) {
                return $payload(403, $e->getMessage() ?: 'Forbidden');
            }

            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException
                || $e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
                return $payload(404, 'Not Found');
            }

            if ($e instanceof \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException) {
                return $payload(405, 'Method Not Allowed');
            }

            if ($e instanceof \Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException) {
                return $payload(429, 'Too Many Requests');
            }

            return $payload(500, 'Server Error');
        });
    })


    // bootstrap/app.php (قسم withExceptions)
// ->withExceptions(function (Exceptions $exceptions) {
//     $exceptions->render(function (\Throwable $e, $request) {
//         if (! $request->is('api/*')) return null;

//         $payload = fn ($status, $message, $errors = null) => response()->json([
//             'success' => false,
//             'message' => $message,
//             'errors'  => $errors,
//         ], $status);

//         // ... باقي الهاندلرز ...

//         // 500
//         $message = app()->environment('production')
//             ? 'Server Error'
//             : ('Server Error: '.$e->getMessage());

//         return $payload(500, $message);
//     });
// })

    // ⭐ أضف هذا فقط لتسجيل مزوّد الدفع
    ->withProviders([
        PaymentServiceProvider::class,
    ])
    ->create();
