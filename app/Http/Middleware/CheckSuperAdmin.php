<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class CheckSuperAdmin
{
    public function handle(Request $request, Closure $next): mixed
    {
        // Rate limiting — 10 tentatives par minute par IP
        $key = 'super-admin:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $seconds = RateLimiter::availableIn($key);
            abort(429, "Trop de tentatives. Réessayez dans {$seconds} secondes.");
        }
        RateLimiter::hit($key, 60);

        // Vérification session
        $email = session('super_admin_email');
        $verified = session('super_admin_verified');

        if (! $verified || $email !== config('superadmin.email')) {
            return redirect()->route('super-admin.login');
        }

        RateLimiter::clear($key);

        return $next($request);
    }
}
