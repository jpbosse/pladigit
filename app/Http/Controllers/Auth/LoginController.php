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
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);
 
        // --- Phase 2 : Tenter l'authentification LDAP si configuré ---
        $ldapUser = app(LdapAuthService::class)->authenticate(
            $request->email,
            $request->password
        );
 
        if ($ldapUser) {
            $ldapUser->update([
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
            ]);
            $this->audit->log('user.login_ldap', $ldapUser);
            Auth::login($ldapUser, $request->boolean('remember'));
            $request->session()->regenerate();
 
            if ($ldapUser->totp_enabled) {
                session(['2fa_user_id' => $ldapUser->id]);
                Auth::logout();
                return redirect()->route('2fa.challenge');
            }
            return redirect()->intended(route('dashboard'));
        }
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
        if (! Hash::check($request->password, $user->password_hash)) {
            $this->handleFailedAttempt($user);
            throw ValidationException::withMessages([
                'email' => ['Identifiants incorrects.'],
            ]);
        }
 
        // 4. Réinitialiser les tentatives et logger
        $user->update([
            'login_attempts' => 0,
            'last_login_at'  => now(),
            'last_login_ip'  => $request->ip(),
        ]);
 
        $this->audit->log('user.login', $user);
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
 
        // 5. Si 2FA activé → rediriger vers vérification TOTP (Phase 2)
        if ($user->totp_enabled) {
            session(['2fa_user_id' => $user->id]);
            Auth::logout();
            return redirect()->route('2fa.challenge');
        }
 
        // 6. Changement de mot de passe forcé
        if ($user->force_pwd_change) {
            return redirect()->route('password.change.forced');
        }
 
        return redirect()->intended(route('dashboard'));
    }
 
    private function handleFailedAttempt(User $user): void
    {
        $settings = \App\Models\Tenant\TenantSettings::sole();
        $attempts  = $user->login_attempts + 1;
 
        $update = ['login_attempts' => $attempts];
 
        if ($attempts >= $settings->login_max_attempts) {
            $update['locked_until'] = now()->addMinutes($settings->login_lockout_minutes);
            $update['status']       = 'locked';
        }
 
        $user->update($update);
        $this->audit->log('user.login_failed', $user, ['attempts' => $attempts]);
    }
 
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
