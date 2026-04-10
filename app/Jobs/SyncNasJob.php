<?php

namespace App\Jobs;

use App\Models\Platform\Organization;
use App\Models\Tenant\TenantSettings;
use App\Models\Tenant\User;
use App\Services\MediaService;
use App\Services\TenantManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Synchronisation NAS asynchrone.
 *
 * Dispatché par SettingsController::syncNas() pour éviter le timeout HTTP.
 * Le worker queue (pladigit-queue.service) exécute la sync en arrière-plan.
 *
 * Timeout : 10 min (les grandes photothèques peuvent être longues).
 * Pas de retry : une sync concurrente est bloquée par le Cache::lock() dans
 * MediaService::syncAlbumTree().
 */
class SyncNasJob implements ShouldQueue
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
        private readonly bool $deep = false,
    ) {}

    public function handle(MediaService $mediaService, TenantManager $tenantManager): void
    {
        // Reconnecter au bon tenant (le Job tourne dans un process séparé).
        $org = Organization::where('slug', $this->orgSlug)->first();

        if (! $org) {
            Log::warning('SyncNasJob — organisation introuvable', ['slug' => $this->orgSlug]);

            return;
        }

        $tenantManager->connectTo($org);

        try {
            $owner = User::where('role', 'admin')->first();

            if (! $owner) {
                Log::warning('SyncNasJob — aucun admin trouvé', ['slug' => $this->orgSlug]);

                return;
            }

            $result = $mediaService->syncAlbumTree(
                nasRoot: '',
                owner: $owner,
                deep: $this->deep,
            );

            // Horodatage de la dernière sync réussie.
            TenantSettings::firstOrCreate([])->update(['nas_photo_last_sync_at' => now()]);

            Log::info('SyncNasJob — sync terminée', array_merge(['slug' => $this->orgSlug], $result));

        } catch (\Throwable $e) {
            Log::error('SyncNasJob — erreur sync', [
                'slug' => $this->orgSlug,
                'error' => $e->getMessage(),
            ]);

            // On relève l'exception pour que le Job passe en état "failed"
            // (visible dans les logs et l'interface horizon si installé).
            throw $e;
        }
    }
}
