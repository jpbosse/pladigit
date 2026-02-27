<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Vérifie que l'utilisateur possède le rôle minimum requis.
 * Usage dans les routes : ->middleware('role:dgs')
 */
class CheckRole
{
    private array $hierarchy = [
        'admin' => 1,
        'president' => 2,
        'dgs' => 3,
        'resp_direction' => 4,
        'resp_service' => 5,
        'user' => 6,
    ];

    public function handle(Request $request, Closure $next, string ...$allowedRoles): mixed
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('login');
        }

        $userLevel = $this->hierarchy[$user->role] ?? 99;

        foreach ($allowedRoles as $role) {
            $requiredLevel = $this->hierarchy[$role] ?? 99;
            if ($userLevel <= $requiredLevel) {
                return $next($request);
            }
        }

        abort(403, 'Accès non autorisé.');
    }
}
