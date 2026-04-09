<?php

namespace App\Services\Ged;

use App\Models\Tenant\GedDocument;
use App\Models\Tenant\GedWopiToken;
use App\Models\Tenant\User;
use Illuminate\Support\Str;

/**
 * Génération et validation des tokens WOPI pour Collabora Online.
 */
class WopiTokenService
{
    /**
     * Génère un token WOPI pour un document et un utilisateur.
     * Les tokens expirés du même document/utilisateur sont purgés au passage.
     */
    public function generate(GedDocument $document, User $user): GedWopiToken
    {
        // Purger les tokens expirés pour ne pas accumuler
        GedWopiToken::where('document_id', $document->id)
            ->where('user_id', $user->id)
            ->where('expires_at', '<', now())
            ->delete();

        $ttl = (int) config('collabora.token_ttl', 1800);

        return GedWopiToken::create([
            'document_id' => $document->id,
            'user_id' => $user->id,
            'token' => Str::random(64),
            'expires_at' => now()->addSeconds($ttl),
        ]);
    }

    /**
     * Valide un token WOPI et retourne le modèle hydraté, ou null si invalide/expiré.
     */
    public function validate(string $token): ?GedWopiToken
    {
        /** @var GedWopiToken|null $wopiToken */
        $wopiToken = GedWopiToken::with(['document', 'user'])
            ->where('token', $token)
            ->first();

        if ($wopiToken === null || $wopiToken->isExpired()) {
            return null;
        }

        return $wopiToken;
    }

    /**
     * Construit l'access_token à envoyer à Collabora : "{org_slug}:{raw_token}".
     * Le slug permet au WopiController de résoudre le tenant sans sous-domaine.
     */
    public function buildAccessToken(GedWopiToken $token, string $orgSlug): string
    {
        return $orgSlug.':'.$token->token;
    }

    /**
     * Parse un access_token au format "{org_slug}:{raw_token}".
     * Retourne ['slug' => ..., 'token' => ...] ou null si format invalide.
     *
     * @return array{slug: string, token: string}|null
     */
    public function parseAccessToken(string $accessToken): ?array
    {
        $pos = strpos($accessToken, ':');

        if ($pos === false || $pos === 0) {
            return null;
        }

        return [
            'slug' => substr($accessToken, 0, $pos),
            'token' => substr($accessToken, $pos + 1),
        ];
    }

    /**
     * Timestamp d'expiration absolu en millisecondes (format attendu par Collabora).
     *
     * La spec WOPI définit access_token_ttl comme un timestamp Unix en ms,
     * PAS une durée. Envoyer une durée (ex: 1 800 000) est interprété comme
     * "expire le 01/01/1970 à 00h30" → session immédiatement expirée.
     */
    public function ttlMs(): int
    {
        $ttl = (int) config('collabora.token_ttl', 14400);

        return (int) (now()->addSeconds($ttl)->timestamp * 1000);
    }
}
