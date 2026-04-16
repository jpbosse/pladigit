<?php

namespace App\Jobs;

use App\Models\Platform\Organization;
use App\Models\Tenant\TenantSettings;
use App\Services\BackupService;
use App\Services\TenantManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Exécute une sauvegarde complète pour une organisation en arrière-plan.
 *
 * Dispatch depuis SettingsController::runBackup() (bouton manuel)
 * ou depuis BackupCommand (schedule automatique).
 */
class BackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600; // 1h max

    public int $tries = 1; // Pas de retry — un backup partiel serait pire

    public function __construct(
        private readonly string $orgSlug,
    ) {}

    public function handle(TenantManager $tenantManager, BackupService $backupService): void
    {
        $org = Organization::where('slug', $this->orgSlug)
            ->where('status', 'active')
            ->first();

        if ($org === null) {
            Log::error("BackupJob: organisation {$this->orgSlug} introuvable.");

            return;
        }

        $tenantManager->connectTo($org);
        $settings = TenantSettings::firstOrCreate([]);

        // Marquer comme "en cours"
        $settings->update([
            'backup_last_status' => 'running',
            'backup_last_message' => 'Sauvegarde en cours…',
            'backup_last_run_at' => now(),
        ]);

        try {
            $result = $backupService->run($org, $settings);

            $settings->update([
                'backup_last_status' => $result['ok'] ? 'success' : 'failed',
                'backup_last_message' => $result['message'],
                'backup_last_size_bytes' => $result['size_bytes'],
                'backup_last_run_at' => now(),
            ]);

        } catch (\Throwable $e) {
            Log::error("BackupJob [{$this->orgSlug}]: ".$e->getMessage(), [
                'exception' => $e,
            ]);

            $settings->update([
                'backup_last_status' => 'failed',
                'backup_last_message' => $e->getMessage(),
                'backup_last_run_at' => now(),
            ]);
        }
    }
}
