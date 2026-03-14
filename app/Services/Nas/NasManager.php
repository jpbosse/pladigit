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
 * Colonnes tenant_settings :
 *   nas_photo_driver, nas_photo_local_path, nas_photo_host,
 *   nas_photo_port, nas_photo_username, nas_photo_password_enc,
 *   nas_photo_share, nas_photo_root_path
 */
class NasManager
{
    public function photoDriver(?TenantSettings $settings = null): NasConnectorInterface
    {
        $settings ??= TenantSettings::first();
        $driver = $settings !== null ? ($settings->nas_photo_driver ?? config('nas.default_driver', 'local')) : config('nas.default_driver', 'local');

        return match ($driver) {
            'local' => $this->makeLocalDriver($settings, 'photo'),
            'sftp' => $this->makeSftpDriver($settings, 'photo'),
            'smb' => $this->makeSmbDriver($settings, 'photo'),
            default => throw new RuntimeException("Driver NAS inconnu : {$driver}"),
        };
    }

    public function gedDriver(?TenantSettings $settings = null): NasConnectorInterface
    {
        $settings ??= TenantSettings::first();
        $driver = $settings !== null ? ($settings->nas_ged_driver ?? config('nas.default_driver', 'local')) : config('nas.default_driver', 'local');

        return match ($driver) {
            'local' => $this->makeLocalDriver($settings, 'ged'),
            'sftp' => $this->makeSftpDriver($settings, 'ged'),
            'smb' => $this->makeSmbDriver($settings, 'ged'),
            default => throw new RuntimeException("Driver NAS inconnu : {$driver}"),
        };
    }

    /** @deprecated Utiliser photoDriver() ou gedDriver() */
    public function driver(?TenantSettings $settings = null): NasConnectorInterface
    {
        return $this->photoDriver($settings);
    }

    private function makeLocalDriver(?TenantSettings $settings, string $module): LocalNasDriver
    {
        $field = "nas_{$module}_local_path";
        $path = $settings !== null ? ($settings->$field ?? config('nas.local_path')) : config('nas.local_path');

        return new LocalNasDriver($path ?: null);
    }

    private function makeSftpDriver(?TenantSettings $settings, string $module): SftpNasDriver
    {
        $hostKey = "nas_{$module}_host";
        $portKey = "nas_{$module}_port";
        $usernameKey = "nas_{$module}_username";
        $passwordKey = "nas_{$module}_password_enc";
        $rootKey = "nas_{$module}_root_path";

        if (! $settings?->$hostKey) {
            throw new RuntimeException(
                "Configuration SFTP {$module} incomplète — renseignez l'hôte dans les paramètres NAS."
            );
        }

        $pwd = '';
        if ($settings->$passwordKey) {
            try {
                $pwd = Crypt::decryptString($settings->$passwordKey);
            } catch (\Throwable) {
                throw new RuntimeException("Impossible de déchiffrer le mot de passe NAS {$module} — reconfigurer.");
            }
        }

        return new SftpNasDriver(
            host: $settings->$hostKey,
            port: (int) ($settings->$portKey ?? 22),
            username: $settings->$usernameKey ?? '',
            password: $pwd,
            rootPath: $settings->$rootKey ?? '/',
        );
    }

    private function makeSmbDriver(?TenantSettings $settings, string $module): SmbNasDriver
    {
        $hostKey = "nas_{$module}_host";
        $shareKey = "nas_{$module}_share";
        $usernameKey = "nas_{$module}_username";
        $passwordKey = "nas_{$module}_password_enc";
        $rootKey = "nas_{$module}_root_path";
        $workgroupKey = "nas_{$module}_smb_workgroup";

        if (! $settings?->$hostKey) {
            throw new RuntimeException(
                "Configuration SMB {$module} incomplète — renseignez l'hôte dans les paramètres NAS."
            );
        }

        $pwd = '';
        if ($settings->$passwordKey) {
            try {
                $pwd = Crypt::decryptString($settings->$passwordKey);
            } catch (\Throwable) {
                throw new RuntimeException("Impossible de déchiffrer le mot de passe NAS SMB {$module} — reconfigurer.");
            }
        }

        return new SmbNasDriver(
            host: $settings->$hostKey,
            share: $settings->$shareKey ?? 'public',
            username: $settings->$usernameKey ?? 'guest',
            password: $pwd,
            workgroup: $settings->$workgroupKey ?? 'WORKGROUP',
            rootPath: $settings->$rootKey ?? '',
        );
    }
}
