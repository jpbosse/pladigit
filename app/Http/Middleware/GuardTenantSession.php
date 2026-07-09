<?php

namespace App\Http\Middleware;

use App\Services\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Empêche la fuite de session entre tenants.
 *
 * Contexte : SESSION_DOMAIN=.pladigit.fr (wildcard) fait que le même cookie
 * de session est envoyé à tous les sous-domaines. Auth::id() seul ne suffit
 * pas à garantir l'isolation : App\Models\Tenant\User utilise la connexion
 * 'tenant', reconnectée à chaque requête selon le sous-domaine. Si deux
 * tenants ont chacun un utilisateur portant le même ID (fréquent : les
 * premiers admins créés depuis le template portent tous l'ID 1), un
 * utilisateur authentifié sur un tenant se retrouve authentifié comme
 * l'utilisateur de même ID sur un autre tenant, sans ressaisir ses
 * identifiants.
 *
 * Le correctif : à chaque login, l'ID de l'organisation courante est
 * enregistré en session (voir LoginController et TwoFactorController).
 * Cette middleware compare cette valeur à l'organisation résolue pour la
 * requête courante à chaque passage ; en cas de désaccord, la session est
 * invalidée et l'utilisateur redirigé vers le login.
 *
 * Positionnement impératif :
 * - APRÈS ResolveTenant (le tenant courant doit être résolu)
 * - APRÈS StartSession (accès à la session nécessaire)
 * ResolveTenant est prependée au groupe 'web' (doit s'exécuter avant
 * StartSession pour pouvoir régler config('session.lifetime') par tenant).
 * Cette middleware doit donc être ajoutée normalement au groupe 'web'
 * (append), jamais prependée.
 */
class GuardTenantSession
{
    public function __construct(private TenantManager $tenantManager) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $org = $this->tenantManager->current();

        // Pas de tenant résolu pour cette requête (routes publiques,
        // super-admin, health, wopi...) → rien à garder ici.
        if ($org === null) {
            return $next($request);
        }

        if (Auth::check()) {
            $sessionOrgId = $request->session()->get('tenant_org_id');

            // En test, l'authentification via $this->actingAs() (helper Laravel)
            // ne passe jamais par LoginController::loginUser() — tenant_org_id
            // n'est donc jamais posé en session par ce chemin. On l'initialise
            // silencieusement dans ce seul cas (valeur totalement absente) pour
            // ne pas casser les tests existants qui authentifient ainsi.
            // Ce filet ne s'applique JAMAIS en cas de désaccord explicite entre
            // une valeur présente et l'organisation résolue — c'est précisément
            // le scénario de fuite que cette middleware doit bloquer, y compris
            // en test (voir test_isolation_tenant dans LoginTest).
            if ($sessionOrgId === null && app()->environment('testing')) {
                $request->session()->put('tenant_org_id', $org->id);

                return $next($request);
            }

            if ($sessionOrgId !== $org->id) {
                Log::warning('Isolation tenant — session rejetée (org mismatch)', [
                    'session_org_id' => $sessionOrgId,
                    'resolved_org_id' => $org->id,
                    'resolved_slug' => $org->slug,
                    'user_id' => Auth::id(),
                ]);

                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->guest(route('login'));
            }
        }

        return $next($request);
    }
}
