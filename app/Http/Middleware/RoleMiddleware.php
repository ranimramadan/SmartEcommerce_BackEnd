<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Illuminate\Auth\AuthenticationException;
class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->user();
        if (! $user) {
            throw new AuthenticationException('Unauthenticated');
        }

        if (! method_exists($user, 'hasAnyRole')) {
            throw new \RuntimeException('Role check not available');
        }

        if (! $user->hasAnyRole($roles)) {
            throw new AccessDeniedHttpException('Required role(s): '.implode(',', $roles));
        }

        return $next($request);
    }
}



