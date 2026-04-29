<?php

namespace App\Console\Commands;

use App\Jobs\BackupJob;
use App\Jobs\PlatformBackupJob;
use App\Models\Platform\Organization;
use App\Models\Platform\PlatformSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Lance une sauvegarde pour les organisations actives.
 *
 * La configuration de sauvegarde (driver, chemin, schedule) est lue depuis
 * PlatformSettings — source de vérité unique pour le Super Admin.
 *
 * Usage :
 *   php artisan pladigit:backup               — toutes les orgs (via PlatformBackupJob)
 *   php artisan pladigit:backup --slug=demo   — une organisation précise
 *   php artisan pladigit:backup --force       — force même si backup_enabled = false
 */
class BackupCommand extends Command
{
    protected $signature = 'pladigit:backup
        {--slug= : Slug de l\'organisation (toutes si omis)}
        {--force : Force la sauvegarde même si backup_enabled = false}';

    protected $description = 'Lance la sauvegarde des données Pladigit';

    public function handle(): int
    {
        $slugFilter = $this->option('slug');
        $force = (bool) $this->option('force');

        $platformSettings = PlatformSettings::firstOrCreate([]);

        // Sans --slug ni --force : chemin automatique via PlatformBackupJob
        if (! $slugFilter && ! $force) {
            if (! $platformSettings->backup_enabled) {
                $this->line('Sauvegarde plateforme désactivée dans PlatformSettings.');

                return self::SUCCESS;
            }
            if (! $this->isDue($platformSettings->backup_schedule ?? 'daily')) {
                $this->line("Pas encore l'heure (fréquence: {$platformSettings->backup_schedule}).");

                return self::SUCCESS;
            }
            PlatformBackupJob::dispatch();
            $this->info('Sauvegarde plateforme (toutes orgs) lancée en arrière-plan.');

            return self::SUCCESS;
        }

        // Chemin manuel (--slug / --force) : vérifications platform-wide
        if (! $platformSettings->backupIsConfigured()) {
            $this->error('Sauvegarde non configurée dans PlatformSettings.');

            return self::FAILURE;
        }

        if (! $force && ! $platformSettings->backup_enabled) {
            $this->line('Sauvegarde désactivée dans PlatformSettings (utilisez --force pour forcer).');

            return self::SUCCESS;
        }

        if (! $force && ! $this->isDue($platformSettings->backup_schedule ?? 'daily')) {
            $this->line("Pas encore l'heure (fréquence: {$platformSettings->backup_schedule}).");

            return self::SUCCESS;
        }

        $query = Organization::where('status', 'active');

        if ($slugFilter) {
            $query->where('slug', $slugFilter);
        }

        $orgs = $query->get();

        if ($orgs->isEmpty()) {
            $this->error('Aucune organisation active trouvée.');

            return self::FAILURE;
        }

        $dispatched = 0;

        foreach ($orgs as $org) {
            if (($platformSettings->backup_driver ?? 'local') === 'local') {
                $localPath = trim((string) ($platformSettings->backup_local_path ?? ''));
                if ($localPath !== '') {
                    File::ensureDirectoryExists($localPath.'/'.$org->slug, 0750);
                }
            }

            BackupJob::dispatch($org->slug);
            $this->info("  Sauvegarde lancée pour : {$org->slug}");
            $dispatched++;
        }

        if ($dispatched === 0) {
            $this->warn('Aucune sauvegarde dispatched.');

            return self::SUCCESS;
        }

        $this->info("✓ {$dispatched} sauvegarde(s) lancée(s) en arrière-plan.");

        return self::SUCCESS;
    }

    /**
     * Détermine si la fréquence configurée est due à l'heure courante.
     *
     * Le schedule Laravel appelle cette commande toutes les heures.
     * - hourly  : toujours vrai
     * - daily   : vrai uniquement à minuit (heure 0)
     * - weekly  : vrai uniquement le dimanche à minuit
     */
    private function isDue(string $schedule): bool
    {
        return match ($schedule) {
            'hourly' => true,
            'daily' => (int) date('G') === 0,
            'weekly' => (int) date('N') === 7 && (int) date('G') === 0,
            default => true,
        };
    }
}
