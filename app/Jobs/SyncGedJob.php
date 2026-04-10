<?php

namespace App\Jobs;

use App\Models\Platform\Organization;
use App\Models\Tenant\TenantSettings;
use App\Models\Tenant\User;
use App\Services\Ged\GedStorageInterface;
use App\Services\Ged\GedSyncService;
use App\Services\TenantManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Synchronisation NAS → GED asynchrone.
 *
 * Dispatché par SettingsController::syncGed() pour éviter le timeout HTTP.
 * Le worker queue (pladigit-queue.service) exécute la sync en arrière-plan.
 *
 * Timeout : 10 min (les grandes arborescences peuvent être longues).
 * Pas de retry : une sync concurrente est bloquée par le Cache::lock() dans
 * GedSyncService::syncFolderTree().
 *
 * IMPORTANT : NasManager et GedSyncService sont résolus via app() APRÈS
 * connectTo(), car ils accèdent à TenantSettings sur la connexion tenant.
 */
class SyncGedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Durée max d'exécution en secondes (10 minutes).
     */
    public int $timeout = 600;

    /**
     * Pas de retry automatique — si ça échoue, l'admin relancera manuellement.
     */
    public int $tries = 1;

    public function __construct(
        private readonly string $orgSlug,
        private readonly string $nasRoot = '',
    ) {}

    public function handle(TenantManager $tenantManager): void
    {
        $org = Organization::where('slug', $this->orgSlug)->first();

        if (! $org) {
            Log::warning('SyncGedJob — organisation introuvable', ['slug' => $this->orgSlug]);

            return;
        }

        $tenantManager->connectTo($org);

        // Résolution APRÈS connectTo() — TenantSettings accessible sur la connexion tenant.
        $storage = app(GedStorageInterface::class);
        $syncService = app(GedSyncService::class);

        try {
            $owner = User::where('role', 'admin')->first();

            if (! $owner) {
                Log::warning('SyncGedJob — aucun admin trouvé', ['slug' => $this->orgSlug]);

                return;
            }

            $result = $syncService->syncFolderTree($storage, $this->nasRoot, $owner);

            TenantSettings::firstOrCreate([])->update([
                'nas_ged_last_sync_at' => now(),
                'nas_ged_last_sync_errors' => $result['error_details'],
            ]);

            Log::info('SyncGedJob — sync terminée', array_merge(['slug' => $this->orgSlug], $result));

        } catch (\Throwable $e) {
            Log::error('SyncGedJob — erreur sync', [
                'slug' => $this->orgSlug,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
