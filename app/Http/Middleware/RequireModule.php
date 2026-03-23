<?php

namespace App\Http\Middleware;

use App\Enums\ModuleKey;
use App\Services\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Vérifie qu'un module Pladigit est activé pour l'organisation courante.
 *
 * Enregistrement dans bootstrap/app.php :
 *   'module' => RequireModule::class
 *
 * Usage dans les routes :
 *   Route::middleware('module:media')->group(...)
 *   Route::middleware('module:ged')->group(...)
 *
 * Comportement :
 *   - Organisation non résolue (hors tenant) → laisse passer (guard du ResolveTenant)
 *   - Module inconnu                          → abort 404 (sécurité)
 *   - Module désactivé                        → abort 403 avec message clair
 *   - Module activé                           → next()
 */
class RequireModule
{
    public function __construct(private TenantManager $tenantManager) {}

    public function handle(Request $request, Closure $next, string $moduleKey): Response
    {
        $org = $this->tenantManager->current();

        // Hors contexte tenant (super-admin, routes platform) → laisser passer
        if ($org === null) {
            return $next($request);
        }

        // Valider que la clé correspond à un module connu
        $module = ModuleKey::tryFrom($moduleKey);
        if ($module === null) {
            abort(404, "Module inconnu : {$moduleKey}");
        }

        // Vérifier l'activation sur l'organisation courante
        if (! $org->hasModule($module)) {
            abort(403, "Le module « {$module->label()} » n'est pas activé pour votre organisation.");
        }

        return $next($request);
    }
}
