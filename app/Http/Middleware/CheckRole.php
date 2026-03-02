<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Vérifie que l'utilisateur possède le rôle minimum requis.
 * Usage dans les routes : ->middleware('role:dgs')
 *
 * La hiérarchie est définie dans UserRole (§17.7 CDC v1.2).
 */
class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$allowedRoles): mixed
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('login');
        }

        $userRole = UserRole::tryFrom($user->role);

        foreach ($allowedRoles as $role) {
            $requiredRole = UserRole::tryFrom($role);

            if ($userRole && $requiredRole && $userRole->atLeast($requiredRole)) {
                return $next($request);
            }
        }

        abort(403, 'Accès non autorisé.');
    }
}
