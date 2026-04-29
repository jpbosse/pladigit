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
 * Sauvegarde toutes les organisations actives depuis le niveau super-admin.
 *
 * Utilise la configuration stockée dans platform_settings (une seule destination
 * commune pour toute la plateforme). Chaque organisation génère une archive
 * horodatée distincte dans la même destination.
 */
class PlatformBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 7200; // 2h max (toutes les orgs)

    public int $tries = 1;

    public function handle(TenantManager $tenantManager, BackupService $backupService): void
    {
        $platformSettings = PlatformSettings::firstOrCreate([]);

        $orgs = Organization::where('status', 'active')->get();

        if ($orgs->isEmpty()) {
            $this->updateStatus($platformSettings, 'failed', 'Aucune organisation active.');

            return;
        }

        $this->updateStatus($platformSettings, 'running', 'Sauvegarde en cours…');

        $errors = [];
        $totalSize = 0;

        foreach ($orgs as $org) {
            try {
                $tenantManager->connectTo($org);

                // Proxy TenantSettings alimenté par PlatformSettings,
                // avec sous-répertoire isolé par organisation pour le driver local.
                $proxySettings = $this->buildProxySettings($platformSettings, $org->slug);

                $result = $backupService->run($org, $proxySettings);

                if ($result['ok']) {
                    $totalSize += $result['size_bytes'];
                } else {
                    $errors[] = $org->slug.': '.$result['message'];
                }
            } catch (\Throwable $e) {
                Log::error("PlatformBackupJob [{$org->slug}]: ".$e->getMessage());
                $errors[] = $org->slug.': '.$e->getMessage();
            }
        }

        if (empty($errors)) {
            $this->updateStatus(
                $platformSettings,
                'success',
                count($orgs).' organisation(s) sauvegardée(s) — '.number_format($totalSize / 1024 / 1024, 1).' Mo total.',
                $totalSize
            );
        } else {
            $this->updateStatus(
                $platformSettings,
                'failed',
                'Erreurs sur '.count($errors).' org(s) : '.implode(' | ', $errors)
            );
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

    private function updateStatus(
        PlatformSettings $settings,
        string $status,
        string $message,
        int $sizeBytes = 0
    ): void {
        $settings->update([
            'backup_last_status' => $status,
            'backup_last_message' => $message,
            'backup_last_run_at' => now(),
            'backup_last_size_bytes' => $sizeBytes ?: null,
        ]);
    }
}
