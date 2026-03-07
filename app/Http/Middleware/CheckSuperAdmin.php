<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class CheckSuperAdmin
{
    // IPs autorisées — ajouter les IPs de production ici
    private array $allowedIps = [
        '127.0.0.1',
        '::1',
    ];

    public function handle(Request $request, Closure $next): mixed
    {
        // 1. Restriction IP
        if (! in_array($request->ip(), $this->allowedIps)) {
            abort(403, 'Accès refusé.');
        }

        // 2. Rate limiting — 5 tentatives par minute par IP
        $key = 'super-admin:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            abort(429, "Trop de tentatives. Réessayez dans {$seconds} secondes.");
        }
        RateLimiter::hit($key, 60);

        // 3. Vérification session
        $email = session('super_admin_email');
        $verified = session('super_admin_verified');

        if (! $verified || $email !== config('superadmin.email')) {
            return redirect()->route('super-admin.login');
        }

        // Réinitialiser le compteur après succès
        RateLimiter::clear($key);

        return $next($request);
    }
}
