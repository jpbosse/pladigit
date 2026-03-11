<?php

namespace App\Http\Controllers;

use App\Models\Tenant\TenantSettings;
use App\Services\AuditService;
use App\Services\PasswordPolicyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;

/**
 * Gestion du profil utilisateur connecté.
 * §18.4 — CDC v1.2
 */
class ProfileController extends Controller
{
    public function __construct(
        private PasswordPolicyService $policy,
        private AuditService $audit,
    ) {}

    /**
     * Affiche la page profil.
     */
    public function show()
    {
        $user = Auth::user();
        $settings = TenantSettings::firstOrCreate([]);

        // Nombre de codes de secours restants
        $backupCodesCount = null;
        if ($user->totp_enabled && $user->totp_backup_code_enc) {
            try {
                $codes = json_decode(Crypt::decryptString($user->totp_backup_code_enc), true);
                $backupCodesCount = count($codes ?? []);
            } catch (\Throwable) {
                $backupCodesCount = 0;
            }
        }

        // Date d'expiration du mot de passe
        $passwordExpiresIn = null;
        if ($settings->pwd_validity_days && $user->password_changed_at) {
            $expiresAt = $user->password_changed_at->addDays($settings->pwd_validity_days);
            $passwordExpiresIn = now()->diffInDays($expiresAt, false); // négatif si expiré
        }

        return view('profile.show', [
            'user' => $user,
            'settings' => $settings,
            'backupCodesCount' => $backupCodesCount,
            'passwordExpiresIn' => $passwordExpiresIn,
        ]);
    }

    /**
     * Met à jour les informations personnelles (nom, département).
     */
    public function updateInfo(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
        ]);

        $user = Auth::user();
        $old = $user->only(['name', 'department']);

        $user->update([
            'name' => $request->name,
            'department' => $request->department,
        ]);

        $this->audit->log('user.profile_updated', $user, [
            'old' => $old,
            'new' => $user->only(['name', 'department']),
        ]);

        return back()->with('success_info', 'Informations mises à jour.');
    }

    /**
     * Met à jour le mot de passe depuis le profil.
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed'],
        ]);

        $user = Auth::user();

        if (! Hash::check($request->current_password, $user->password_hash)) {
            return back()->withErrors(['current_password' => 'Mot de passe actuel incorrect.']);
        }

        $history = $user->password_history ?? [];
        $history[] = $user->password_hash;
        $policyErrors = $this->policy->validate($request->password, $history);

        if (! empty($policyErrors)) {
            return back()->withErrors(['password' => $policyErrors]);
        }

        $this->policy->updatePassword($user, $request->password);
        $this->audit->log('user.password_changed', $user);

        return back()->with('success_password', 'Mot de passe modifié avec succès.');
    }

    /**
     * Régénère les codes de secours 2FA.
     * Les nouveaux codes sont affichés UNE SEULE FOIS via la session.
     */
    public function regenerateBackupCodes(Request $request)
    {
        $request->validate(['password' => ['required', 'string']]);

        $user = Auth::user();

        if (! $user->totp_enabled) {
            return back()->withErrors(['password' => 'Le 2FA n\'est pas activé.']);
        }

        if (! Hash::check($request->password, $user->password_hash)) {
            return back()->withErrors(['password' => 'Mot de passe incorrect.']);
        }

        $codes = $this->generateBackupCodes();

        $user->update([
            'totp_backup_code_enc' => Crypt::encryptString(
                json_encode(array_map(
                    fn ($c) => hash('sha256', $c),
                    $codes
                ))
            ),
        ]);

        $this->audit->log('user.backup_codes_regenerated', $user);

        // Codes affichés une seule fois via session flash
        return back()->with('new_backup_codes', $codes);
    }

    /**
     * Génère N codes de secours aléatoires (format XXXXX-XXXXX).
     */
    private function generateBackupCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(substr(bin2hex(random_bytes(3)), 0, 5))
                     .'-'
                     .strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
        }

        return $codes;
    }
}
