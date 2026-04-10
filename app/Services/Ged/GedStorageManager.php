<?php

namespace App\Services\Ged;

use App\Models\Tenant\TenantSettings;
use App\Services\Nas\NasManager;

/**
 * GedStorageManager — implémente GedStorageInterface en déléguant
 * au bon backend selon la configuration du tenant (nas_ged_driver).
 *
 * Résolution automatique par le container Laravel :
 *   AppServiceProvider::register() bind GedStorageInterface → GedStorageManager.
 *
 * Drivers disponibles :
 *   - local  → LocalGedDriver  (disk Laravel "local", storage/app/private)
 *   - sftp   → GedNasDriver wrappant SftpNasDriver
 *   - smb    → GedNasDriver wrappant SmbNasDriver
 */
class GedStorageManager implements GedStorageInterface
{
    private GedStorageInterface $driver;

    public function __construct(NasManager $nasManager)
    {
        $settings = TenantSettings::firstOrNew();
        $driverName = $settings->nas_ged_driver ?? 'local';

        $this->driver = match ($driverName) {
            'sftp', 'smb' => new GedNasDriver($nasManager->gedDriver($settings)),
            default => new LocalGedDriver($settings->nas_ged_local_path ?? ''),
        };
    }

    // ── Délégation vers le driver résolu ────────────────────

    public function put(string $path, mixed $contents): bool
    {
        return $this->driver->put($path, $contents);
    }

    public function get(string $path): string|false
    {
        return $this->driver->get($path);
    }

    public function readStream(string $path): mixed
    {
        return $this->driver->readStream($path);
    }

    public function delete(string $path): bool
    {
        return $this->driver->delete($path);
    }

    public function exists(string $path): bool
    {
        return $this->driver->exists($path);
    }

    public function size(string $path): int
    {
        return $this->driver->size($path);
    }

    public function mimeType(string $path): string|false
    {
        return $this->driver->mimeType($path);
    }

    public function mkdir(string $path): bool
    {
        return $this->driver->mkdir($path);
    }

    public function listDirectory(string $path): array
    {
        return $this->driver->listDirectory($path);
    }

    /**
     * Retourne le nom du driver actif.
     * Utilisé par la vue settings pour afficher le statut.
     */
    public function driverName(): string
    {
        return match (true) {
            $this->driver instanceof GedNasDriver => 'nas',
            $this->driver instanceof LocalGedDriver => 'local',
            default => 'inconnu',
        };
    }
}
