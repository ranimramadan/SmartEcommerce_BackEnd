<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
class ActiveUserMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if ($user = $request->user()) {
            if (! $user->is_active) {
                throw new AccessDeniedHttpException('Account disabled'); // بدل return json
            }
        }
        return $next($request);
    }
}



