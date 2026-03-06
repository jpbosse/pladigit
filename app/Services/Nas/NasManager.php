<?php

namespace App\Services\Nas;

use App\Models\Tenant\TenantSettings;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;

/**
 * NasManager — instancie le bon driver NAS selon la configuration du tenant.
 *
 * Deux modules distincts :
 *   - photoDriver() → NAS de la photothèque (nas_photo_*)
 *   - gedDriver()   → NAS de la GED (nas_ged_*) — Phase 5
 *
 * Drivers disponibles :
 *   - local → LocalNasDriver  (dev/test)
 *   - sftp  → SftpNasDriver   (Linux/NAS via SSH)
 *   - smb   → SmbNasDriver    (Windows/NAS via Samba — Phase 4)
 */
class NasManager
{
    // ─────────────────────────────────────────────────────────────
    //  Points d'entrée publics
    // ─────────────────────────────────────────────────────────────

    /**
     * Driver NAS pour la photothèque (nas_photo_*).
     */
    public function photoDriver(?TenantSettings $settings = null): NasConnectorInterface
    {
        $settings ??= TenantSettings::first();
        $driver = $settings?->nas_photo_driver ?? config('nas.default_driver', 'local');

        return match ($driver) {
            'local' => $this->makeLocalDriver($settings, 'photo'),
            'sftp'  => $this->makeSftpDriver($settings, 'photo'),
            'smb'   => $this->makeSmbDriver(),
            default => throw new RuntimeException("Driver NAS inconnu : {$driver}"),
        };
    }

    /**
     * Driver NAS pour la GED (nas_ged_*) — Phase 5.
     */
    public function gedDriver(?TenantSettings $settings = null): NasConnectorInterface
    {
        $settings ??= TenantSettings::first();
        $driver = $settings?->nas_ged_driver ?? config('nas.default_driver', 'local');

        return match ($driver) {
            'local' => $this->makeLocalDriver($settings, 'ged'),
            'sftp'  => $this->makeSftpDriver($settings, 'ged'),
            'smb'   => $this->makeSmbDriver(),
            default => throw new RuntimeException("Driver NAS inconnu : {$driver}"),
        };
    }

    /**
     * @deprecated Utiliser photoDriver() ou gedDriver()
     */
    public function driver(?TenantSettings $settings = null): NasConnectorInterface
    {
        return $this->photoDriver($settings);
    }

    // ─────────────────────────────────────────────────────────────
    //  Factories privées
    // ─────────────────────────────────────────────────────────────

    private function makeLocalDriver(?TenantSettings $settings, string $module): LocalNasDriver
    {
        $field = "nas_{$module}_local_path";
        $path  = $settings?->$field ?? config('nas.local_path');
        return new LocalNasDriver($path ?: null);
    }

    private function makeSftpDriver(?TenantSettings $settings, string $module): SftpNasDriver
    {
        $host     = "nas_{$module}_host";
        $port     = "nas_{$module}_port";
        $username = "nas_{$module}_username";
        $password = "nas_{$module}_password_enc";
        $root     = "nas_{$module}_root_path";

        if (! $settings || ! $settings->$host) {
            throw new RuntimeException(
                "Configuration SFTP {$module} incomplète — renseignez l'hôte, le port et les identifiants."
            );
        }

        $pwd = '';
        if ($settings->$password) {
            try {
                $pwd = Crypt::decryptString($settings->$password);
            } catch (\Throwable) {
                throw new RuntimeException("Impossible de déchiffrer le mot de passe NAS {$module} — reconfigurer.");
            }
        }

        return new SftpNasDriver(
            host:     $settings->$host,
            port:     (int) ($settings->$port ?? 22),
            username: $settings->$username ?? '',
            password: $pwd,
            rootPath: $settings->$root ?? '/',
        );
    }

    private function makeSmbDriver(): NasConnectorInterface
    {
        throw new RuntimeException(
            'Driver SMB non encore implémenté — utilisez SFTP ou Local pour le moment.'
        );
    }
}
