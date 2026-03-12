<?php

namespace App\Services;

use App\Mail\UserInvitationMail;
use App\Models\Tenant\TenantSettings;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\Mail;

/**
 * Gère la génération, l'envoi et la validation des tokens d'invitation utilisateur.
 *
 * Cycle de vie d'un token :
 *   1. generate()     → token brut (64 chars hex) retourné UNE SEULE FOIS,
 *                        hash SHA-256 stocké en base + expiration 72h
 *   2. sendInvitation() → email avec lien contenant le token brut
 *   3. findValidUser() → vérifie hash + expiration + non utilisé
 *   4. consume()       → remplit invitation_used_at → token invalidé définitivement
 *
 * Sécurité :
 *   - Le token brut ne transite jamais en base de données.
 *   - Le hash SHA-256 est résistant aux attaques par rainbow table sur les tokens
 *     (entropie de 256 bits → infaisable à inverser).
 *   - Un token utilisé est marqué immédiatement (avant la transaction MDP)
 *     pour éviter les race conditions.
 */
class InvitationService
{
    private const TTL_HOURS = 72;

    private const TOKEN_BYTES = 32; // 32 bytes → 64 chars hex

    /**
     * Génère un token d'invitation, le hashe et le stocke sur l'utilisateur.
     *
     * @return string Le token brut à inclure dans le lien email (ne plus stocker après)
     */
    public function generate(User $user): string
    {
        $token = bin2hex(random_bytes(self::TOKEN_BYTES));

        $user->update([
            'invitation_token' => hash('sha256', $token),
            'invitation_expires_at' => now()->addHours(self::TTL_HOURS),
            'invitation_used_at' => null,
            // Le compte reste inactif jusqu'à l'activation
            'status' => 'inactive',
            // Pas de mot de passe en clair — sera défini lors de l'activation
            'password_hash' => null,
        ]);

        return $token;
    }

    /**
     * Envoie l'email d'invitation avec le lien d'activation.
     */
    public function sendInvitation(User $user, string $token, string $invitedByName): void
    {
        $settings = TenantSettings::on('tenant')->firstOrCreate([]);
        $orgName = $settings->org_name ?? 'Pladigit';

        $activationUrl = route('invitation.accept', ['token' => $token]);

        Mail::to($user->email)->send(new UserInvitationMail(
            user: $user,
            token: $token,
            activationUrl: $activationUrl,
            orgName: $orgName,
            invitedByName: $invitedByName,
        ));
    }

    /**
     * Retrouve un utilisateur à partir d'un token brut.
     * Retourne null si le token est invalide, expiré ou déjà utilisé.
     */
    public function findValidUser(string $token): ?User
    {
        $hash = hash('sha256', $token);

        $user = User::on('tenant')
            ->where('invitation_token', $hash)
            ->whereNull('invitation_used_at')
            ->where('invitation_expires_at', '>', now())
            ->first();

        return $user;
    }

    /**
     * Invalide le token après utilisation et active le compte.
     * Doit être appelé en même temps que le hashage du mot de passe.
     */
    public function consume(User $user): void
    {
        $user->update([
            'invitation_used_at' => now(),
            'invitation_token' => null,
            'status' => 'active',
            'force_pwd_change' => false,
        ]);
    }

    /**
     * Vérifie si un token est expiré (pour afficher un message approprié).
     */
    public function isExpired(string $token): bool
    {
        $hash = hash('sha256', $token);

        return User::on('tenant')
            ->where('invitation_token', $hash)
            ->where('invitation_expires_at', '<=', now())
            ->exists();
    }
}
