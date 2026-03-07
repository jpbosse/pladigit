<?php

namespace App\Services;

use App\Models\Tenant\TenantSettings;
use Illuminate\Support\Facades\Hash;

/**
 * Applique la politique de mot de passe définie dans TenantSettings.
 */
class PasswordPolicyService
{
    public function validate(string $password, ?array $history = []): array
    {
        $settings = TenantSettings::firstOrCreate([]);
        $errors = [];

        if (strlen($password) < $settings->pwd_min_length) {
            $errors[] = "Le mot de passe doit contenir au moins {$settings->pwd_min_length} caractères.";
        }

        if ($settings->pwd_require_uppercase && ! preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Le mot de passe doit contenir au moins une majuscule.';
        }

        if ($settings->pwd_require_number && ! preg_match('/[0-9]/', $password)) {
            $errors[] = 'Le mot de passe doit contenir au moins un chiffre.';
        }

        if ($settings->pwd_require_special && ! preg_match('/[\W_]/', $password)) {
            $errors[] = 'Le mot de passe doit contenir au moins un caractère spécial.';
        }

        foreach ($history ?? [] as $oldHash) {
            if (Hash::check($password, $oldHash)) {
                $errors[] = 'Ce mot de passe a déjà été utilisé récemment.';
                break;
            }
        }

        return $errors;
    }

    public function updatePassword(\App\Models\Tenant\User $user, string $newPassword): void
    {
        $settings = TenantSettings::firstOrCreate([]);
        $history = $user->password_history ?? [];

        array_unshift($history, $user->password_hash);
        $history = array_slice($history, 0, $settings->pwd_history_count ?? 5);

        $user->update([
            'password_hash' => Hash::make($newPassword),
            'password_history' => $history,
            'password_changed_at' => now(),
            'force_pwd_change' => false,
        ]);
    }
}
