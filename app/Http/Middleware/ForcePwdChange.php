<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Redirige l'utilisateur vers la page de changement de mot de passe
 * si son compte a le flag force_pwd_change = true.
 *
 * Ce middleware doit être appliqué sur toutes les routes authentifiées.
 * Sans lui, un utilisateur peut accéder directement à /dashboard
 * même avec force_pwd_change = 1, en contournant la redirection du login.
 *
 * Routes autorisées malgré le flag : changement de mot de passe et logout.
 */
class ForcePwdChange
{
    /** Routes accessibles même avec force_pwd_change = true. */
    private array $allowedRoutes = [
        'password.change.forced',
        'password.change.forced.update',
        'logout',
    ];

    public function handle(Request $request, Closure $next): mixed
    {
        $user = Auth::user();

        if ($user && $user->force_pwd_change) {
            $currentRoute = $request->route()?->getName();

            if (! in_array($currentRoute, $this->allowedRoutes, true)) {
                return redirect()->route('password.change.forced')
                    ->with('warning', 'Vous devez changer votre mot de passe avant de continuer.');
            }
        }

        return $next($request);
    }
}
