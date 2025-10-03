<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    protected function redirectTo($request): ?string
    {
        // لا نقوم بإعادة التوجيه إذا كان طلب API
        if (!$request->expectsJson()) {
            return route('login');
        }

        return null;
    }
}
