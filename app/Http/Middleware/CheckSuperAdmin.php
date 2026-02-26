<?php
 
namespace App\Http\Middleware;
 
use Closure;
use Illuminate\Http\Request;
 
/**
 * Vérifie les credentials Super Admin depuis le fichier .env.
 * Le Super Admin n'existe jamais en base de données.
 */
class CheckSuperAdmin
{
    public function handle(Request $request, Closure $next): mixed
    {
        $email    = session('super_admin_email');
        $verified = session('super_admin_verified');
 
        if (! $verified || $email !== env('SUPER_ADMIN_EMAIL')) {
            return redirect()->route('super-admin.login');
        }
 
        return $next($request);
    }
}
