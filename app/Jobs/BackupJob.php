<?php

namespace App\Jobs;

use App\Models\Platform\Organization;
use App\Models\Platform\PlatformSettings;
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
 * La configuration (driver, chemin, SFTP) est lue depuis PlatformSettings.
 * Pour le driver local, l'archive est placée dans backup_local_path/{slug}/.
 *
 * Dispatch depuis BackupCommand (--slug / --force) ou SettingsController::runBackup().
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

        $tenantManager->connectTo($org); // établit la connexion tenant pour le dump DB

        $platformSettings = PlatformSettings::firstOrCreate([]);

        $platformSettings->update([
            'backup_last_status' => 'running',
            'backup_last_message' => "Sauvegarde {$org->slug} en cours…",
            'backup_last_run_at' => now(),
        ]);

        try {
            $proxySettings = $this->buildProxySettings($platformSettings, $org->slug);
            $result = $backupService->run($org, $proxySettings);

            $platformSettings->update([
                'backup_last_status' => $result['ok'] ? 'success' : 'failed',
                'backup_last_message' => $result['message'],
                'backup_last_size_bytes' => $result['size_bytes'],
                'backup_last_run_at' => now(),
            ]);

        } catch (\Throwable $e) {
            Log::error("BackupJob [{$this->orgSlug}]: ".$e->getMessage(), [
                'exception' => $e,
            ]);

            $platformSettings->update([
                'backup_last_status' => 'failed',
                'backup_last_message' => $e->getMessage(),
                'backup_last_run_at' => now(),
            ]);
        }
    }

    /**
     * Construit un TenantSettings proxy alimenté par PlatformSettings.
     * Le chemin local est isolé dans un sous-répertoire par organisation.
     */
    private function buildProxySettings(PlatformSettings $ps, string $orgSlug): TenantSettings
    {
        $localPath = rtrim((string) ($ps->backup_local_path ?? ''), '/');
        if ($localPath !== '') {
            $localPath .= '/'.$orgSlug;
        }

        $proxy = new TenantSettings;
        $proxy->forceFill([
            'backup_driver'            => $ps->backup_driver,
            'backup_local_path'        => $localPath,
            'backup_sftp_host'         => $ps->backup_sftp_host,
            'backup_sftp_port'         => $ps->backup_sftp_port,
            'backup_sftp_user'         => $ps->backup_sftp_user,
            'backup_sftp_password_enc' => $ps->backup_sftp_password_enc,
            'backup_sftp_path'         => $ps->backup_sftp_path,
            'backup_retention_count'   => $ps->backup_retention_count,
        ]);

        return $proxy;
    }
}
