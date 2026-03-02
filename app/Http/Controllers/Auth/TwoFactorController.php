<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant\User;
use App\Services\TwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Gère l'activation, la désactivation et la vérification 2FA.
 *
 * ⚠ RÈGLE DE SÉCURITÉ : Ne jamais logger un code TOTP, un secret
 * ou un code de secours dans ce contrôleur.
 */
class TwoFactorController extends Controller
{
    public function __construct(private TwoFactorService $twoFactor) {}

    // ── Activation ────────────────────────────────────────

    public function setup(Request $request)
    {
        $user = Auth::user();
        $setup = $this->twoFactor->generateSetup($user);

        // Stocker le secret temporairement en session (pas encore en base)
        session(['2fa_setup_secret' => $setup['secret']]);

        return view('auth.2fa.setup', [
            'qr_code' => $setup['qr_code'],
            'secret' => $setup['secret'],
        ]);
    }

    public function confirm(Request $request)
    {
        $request->validate(['code' => ['required', 'digits:6']]);

        $secret = session('2fa_setup_secret');
        $user = Auth::user();

        // Log de contexte sans le secret ni le code
        Log::info('2FA — tentative de confirmation d\'activation', [
            'user_id' => $user->id,
            'has_secret' => ! empty($secret),
        ]);

        if (! $this->twoFactor->enable($user, $secret, $request->code)) {
            return back()->withErrors(['code' => 'Code invalide. Vérifiez l\'heure de votre téléphone.']);
        }

        session()->forget('2fa_setup_secret');

        Log::info('2FA — activé avec succès', ['user_id' => $user->id]);

        return redirect()->route('dashboard')->with('success', '2FA activé avec succès.');
    }

    // ── Vérification lors du login ────────────────────────

    public function challenge()
    {
        if (! session('2fa_user_id')) {
            return redirect()->route('login');
        }

        return view('auth.2fa.challenge');
    }

    public function verify(Request $request)
    {
        $request->validate(['code' => ['required', 'string']]);

        $userId = session('2fa_user_id');

        // Recharger depuis la connexion tenant explicitement
        $user = User::on('tenant')->withoutGlobalScopes()->find($userId);

        // Log de contexte — sans le code saisi
        Log::info('2FA — tentative verify', [
            'user_id' => $userId,
            'found' => $user !== null,
            'totp_enabled' => $user?->totp_enabled,
            'has_secret' => ! empty($user?->totp_secret_enc),
        ]);

        if (! $this->twoFactor->verify($user, $request->code)) {
            return back()->withErrors(['code' => 'Code invalide ou expiré.']);
        }

        session()->forget('2fa_user_id');
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    // ── Désactivation ─────────────────────────────────────

    public function disable(Request $request)
    {
        $request->validate(['password' => ['required']]);

        $user = Auth::user();
        if (! \Hash::check($request->password, $user->password_hash)) {
            return back()->withErrors(['password' => 'Mot de passe incorrect.']);
        }

        $this->twoFactor->disable($user);

        Log::info('2FA — désactivé', ['user_id' => $user->id]);

        return redirect()->route('dashboard')->with('success', '2FA désactivé.');
    }
}
