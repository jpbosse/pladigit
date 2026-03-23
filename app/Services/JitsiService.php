<?php

namespace App\Services;

use App\Models\Tenant\TenantSettings;

/**
 * Génère les URLs de visioconférence Jitsi Meet.
 *
 * Instance par défaut : meet.numerique.gouv.fr
 * Configurable par tenant via tenant_settings.jitsi_base_url
 */
class JitsiService
{
    /**
     * Génère une URL de salle unique pour un projet.
     *
     * Format : {base_url}/pladigit-{slug}-{random6}
     * Exemple : https://meet.numerique.gouv.fr/pladigit-demo-a3f9k2
     */
    public function roomUrl(string $projectSlug): string
    {
        $base = $this->baseUrl();
        $token = substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(8))), 0, 6);
        $room = 'pladigit-'.$this->sanitizeSlug($projectSlug).'-'.strtolower($token);

        return rtrim($base, '/').'/'.$room;
    }

    /**
     * URL de base configurée pour le tenant courant.
     */
    public function baseUrl(): string
    {
        try {
            $settings = TenantSettings::on('tenant')->first();
            $url = $settings?->jitsi_base_url;

            return $url && filter_var($url, FILTER_VALIDATE_URL)
                ? $url
                : 'https://meet.numerique.gouv.fr';
        } catch (\Throwable) {
            return 'https://meet.numerique.gouv.fr';
        }
    }

    /**
     * Nettoie un slug pour l'URL Jitsi (alphanumérique + tirets).
     */
    private function sanitizeSlug(string $slug): string
    {
        $clean = preg_replace('/[^a-z0-9\-]/', '', strtolower($slug));

        return substr($clean ?: 'projet', 0, 30);
    }
}
