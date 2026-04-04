<?php

namespace App\Http\Middleware;

use App\Enums\GedPermissionLevel;
use App\Models\Tenant\GedFolder;
use App\Models\Tenant\User;
use App\Services\Ged\GedPermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Vérifie que l'utilisateur authentifié a le niveau de permission requis
 * sur le dossier GED ciblé par la route.
 *
 * Usage dans les routes :
 *   ->middleware('ged.permission:view')
 *   ->middleware('ged.permission:upload')
 *   ->middleware('ged.permission:admin')
 *
 * Le dossier est résolu via le paramètre de route {folder}.
 * Si aucun paramètre {folder} n'est présent, le middleware est ignoré.
 */
class RequireGedPermission
{
    public function __construct(private readonly GedPermissionService $permissions) {}

    public function handle(Request $request, Closure $next, string $required = 'view'): Response
    {
        $level = GedPermissionLevel::tryFrom($required);

        if ($level === null) {
            abort(500, "Niveau de permission GED invalide : {$required}");
        }

        /** @var GedFolder|null $folder */
        $folder = $request->route('folder');

        if (! ($folder instanceof GedFolder)) {
            return $next($request);
        }

        /** @var User $user */
        $user = $request->user();

        if (! $this->permissions->can($user, $folder, $level)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Permission insuffisante sur ce dossier.'], 403);
            }

            abort(403, 'Permission insuffisante sur ce dossier.');
        }

        return $next($request);
    }
}
