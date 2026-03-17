<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant\User;
use App\Services\AuditService;
use App\Services\LdapAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Gère le login local (email + mot de passe bcrypt) et LDAP (Phase 2).
 *
 * Stratégie de fallback LDAP :
 *   1. Si LDAP non configuré         → auth locale directement (comportement normal)
 *   2. Si LDAP configuré et succès   → connexion LDAP
 *   3. Si LDAP configuré, bind échoué (mauvais mdp) → erreur, PAS de fallback local
 *   4. Si LDAP configuré, user non trouvé            → erreur, PAS de fallback local
 *   5. Si LDAP configuré mais serveur indisponible   → fallback local gracieux (warning loggé)
 */
class LoginController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // --- Phase 2 : Tenter l'authentification LDAP si configuré ---
        $ldapService = app(LdapAuthService::class);
        $ldapUser = $ldapService->authenticate($request->email, $request->password);

        if ($ldapUser) {
            return $this->loginUser($ldapUser, $request, ldap: true);
        }

        // Si LDAP a échoué pour une raison sécurité (mauvais mdp, user inconnu),
        // on bloque uniquement si c'est un compte LDAP connu en base.
        // Un compte local pur doit toujours pouvoir se connecter via auth locale.
        $ldapReason = $ldapService->getLastFailureReason();

        if ($ldapReason === 'bind_failed') {
            // Mauvais mot de passe LDAP → on bloque, pas de fallback local
            throw ValidationException::withMessages([
                'email' => ['Identifiants incorrects.'],
            ]);
        }

        if ($ldapReason === 'user_not_found') {
            // Utilisateur non trouvé dans l'annuaire LDAP
            // Si c'est un compte LDAP en base → on bloque
            // Si c'est un compte local pur → on laisse passer vers l'auth locale
            $localUser = User::where('email', $request->email)->first();
            if ($localUser && $localUser->ldap_dn) {
                throw ValidationException::withMessages([
                    'email' => ['Identifiants incorrects.'],
                ]);
            }
        }

        // À ce stade : LDAP non configuré ('not_configured') ou serveur indisponible ('unavailable')
        // Dans les deux cas, on laisse passer vers l'authentification locale.
        // --- Fin Phase 2 ---

        $user = User::where('email', $request->email)->first();

        // 1. Vérifier l'existence et le statut
        if (! $user || $user->status === 'inactive') {
            throw ValidationException::withMessages([
                'email' => ['Identifiants incorrects.'],
            ]);
        }

        // 2. Vérifier le verrouillage
        if ($user->isLocked()) {
            $this->audit->log('user.login_blocked', $user);
            throw ValidationException::withMessages([
                'email' => ['Compte temporairement bloqué. Réessayez plus tard.'],
            ]);
        }

        // 3. Vérifier le mot de passe (bcrypt)
        //    Si LDAP était configuré mais indisponible et que l'utilisateur est un compte
        //    LDAP pur (pas de password_hash), on refuse — il ne peut pas se connecter sans LDAP.
        if (! $user->password_hash) {
            throw ValidationException::withMessages([
                'email' => ['Ce compte nécessite le serveur LDAP pour se connecter.'],
            ]);
        }

        if (! Hash::check($request->password, $user->password_hash)) {
            $this->handleFailedAttempt($user);
            throw ValidationException::withMessages([
                'email' => ['Identifiants incorrects.'],
            ]);
        }

        // 4. Réinitialiser les tentatives et logger
        $user->update([
            'login_attempts' => 0,
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        $this->audit->log('user.login', $user);

        return $this->loginUser($user, $request, ldap: false);
    }

    /**
     * Finalise la connexion après authentification réussie (locale ou LDAP).
     */
    private function loginUser(User $user, Request $request, bool $ldap = false): \Illuminate\Http\RedirectResponse
    {
        if ($ldap) {
            $user->update([
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
            ]);
            $this->audit->log('user.login_ldap', $user);
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        // 2FA
        if ($user->totp_enabled) {
            Auth::logout();
            $request->session()->put('2fa_user_id', $user->id);

            return redirect()->route('2fa.challenge');
        }

        // Changement de mot de passe forcé (comptes locaux uniquement)
        if (! $ldap && $user->force_pwd_change) {
            return redirect()->route('password.change.forced');
        }

        return redirect()->intended(route('dashboard'));
    }

    private function handleFailedAttempt(User $user): void
    {
        $settings = \App\Models\Tenant\TenantSettings::firstOrCreate([]);
        $attempts = $user->login_attempts + 1;

        $update = ['login_attempts' => $attempts];

        if ($attempts >= $settings->login_max_attempts) {
            $update['locked_until'] = now()->addMinutes($settings->login_lockout_minutes);
            $update['status'] = 'locked';
        }

        $user->update($update);
        $this->audit->log('user.login_failed', $user, ['attempts' => $attempts]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->away('http://pladigit.fr');
    }
}
