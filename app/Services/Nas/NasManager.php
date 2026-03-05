<?php

namespace App\Services\Nas;

use App\Models\Tenant\TenantSettings;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;

/**
 * NasManager — instancie le bon driver NAS selon la configuration du tenant.
 *
 * Drivers disponibles :
 *   - local  → LocalNasDriver (dev/test, pas de NAS requis)
 *   - sftp   → SftpNasDriver  (Phase 3 production Linux/NAS)
 *   - smb    → SmbNasDriver   (Phase 3 production Windows/NAS)
 *
 * Usage :
 *   $nas = app(NasManager::class)->driver();
 *   $nas->testConnection();
 *   $nas->listFiles('photos/2026');
 */
class NasManager
{
    /**
     * Résout et retourne le driver NAS pour le tenant courant.
     */
    public function driver(?TenantSettings $settings = null): NasConnectorInterface
    {
        $settings ??= TenantSettings::first();

        $driver = $settings?->nas_driver ?? config('nas.default_driver', 'local');

        return match ($driver) {
            'local' => $this->makeLocalDriver($settings),
            'sftp'  => $this->makeSftpDriver($settings),
            'smb'   => $this->makeSmbDriver($settings),
            default => throw new RuntimeException("Driver NAS inconnu : {$driver}"),
        };
    }

    // -------------------------------------------------------------------------
    // Factories privées
    // -------------------------------------------------------------------------

    private function makeLocalDriver(?TenantSettings $settings): LocalNasDriver
    {
        $path = $settings?->nas_local_path ?? config('nas.local_path');

        return new LocalNasDriver($path ?: null);
    }

    private function makeSftpDriver(TenantSettings $settings): NasConnectorInterface
    {
        // À implémenter en Phase 3 production
        // Dépendance : league/flysystem-sftp-v3
        throw new RuntimeException('Driver SFTP non encore implémenté — Phase 3 production.');
    }

    private function makeSmbDriver(TenantSettings $settings): NasConnectorInterface
    {
        // À implémenter en Phase 3 production
        // Dépendance : icewind/smb
        throw new RuntimeException('Driver SMB non encore implémenté — Phase 3 production.');
    }
}
