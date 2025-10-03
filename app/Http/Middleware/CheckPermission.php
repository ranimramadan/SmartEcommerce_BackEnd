<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Str;
class CheckPermission
{
    public function handle(Request $request, Closure $next, ...$permissions)
    {
        $user = $request->user();
        if (! $user) {
            throw new AuthenticationException('Unauthenticated');
        }

        if (! method_exists($user, 'hasAnyPermission')) {
            // 500 عام، خليه يروح للـhandler
            throw new \RuntimeException('Permission check not available');
        }

        // (اختياري) تطبيع للسلاجز لو عندك شرطات
        $permissions = collect($permissions)->map(fn($p) => Str::slug($p, '_'))->all();

        if (! $user->hasAnyPermission($permissions)) {
            throw new AccessDeniedHttpException('Need any of: '.implode(',', $permissions));
        }

        return $next($request);
    }
}



