<?php

namespace App\Console\Commands;

use App\Jobs\BackupJob;
use App\Models\Platform\Organization;
use App\Models\Tenant\TenantSettings;
use App\Services\TenantManager;
use Illuminate\Console\Command;

/**
 * Lance une sauvegarde pour les organisations dont la sauvegarde est activée.
 *
 * Usage :
 *   php artisan pladigit:backup               — toutes les orgs actives avec backup_enabled
 *   php artisan pladigit:backup --slug=demo   — une organisation précise
 *   php artisan pladigit:backup --force       — force même si backup_enabled = false
 */
class BackupCommand extends Command
{
    protected $signature = 'pladigit:backup
        {--slug= : Slug de l\'organisation (toutes si omis)}
        {--force : Force la sauvegarde même si backup_enabled = false}';

    protected $description = 'Lance la sauvegarde des données Pladigit';

    public function handle(TenantManager $tenantManager): int
    {
        $slugFilter = $this->option('slug');
        $force = (bool) $this->option('force');

        // Sans --slug : utiliser PlatformSettings comme source de vérité
        if (! $slugFilter && ! $force) {
            $platformSettings = \App\Models\Platform\PlatformSettings::firstOrCreate([]);
            if (! $platformSettings->backup_enabled) {
                $this->line('Sauvegarde plateforme désactivée dans PlatformSettings.');

                return self::SUCCESS;
            }
            if (! $this->isDue($platformSettings->backup_schedule ?? 'daily')) {
                $this->line("Pas encore l'heure (fréquence: {$platformSettings->backup_schedule}).");

                return self::SUCCESS;
            }
            // Dispatcher le job plateforme qui gère toutes les orgs en un coup
            \App\Jobs\PlatformBackupJob::dispatch();
            $this->info('Sauvegarde plateforme (toutes orgs) lancée en arrière-plan.');

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
            $tenantManager->connectTo($org);
            $settings = TenantSettings::firstOrCreate([]);

            if (! $force && ! $settings->backup_enabled) {
                $this->line("  Ignoré : {$org->slug} (sauvegarde désactivée)");

                continue;
            }

            if (! $settings->backupIsConfigured()) {
                $this->warn("  Ignoré : {$org->slug} (destination non configurée)");

                continue;
            }

            // Filtre de fréquence : le schedule tourne toutes les heures,
            // mais "daily" ne déclenche qu'à minuit et "weekly" le dimanche à minuit.
            if (! $force && ! $this->isDue($settings->backup_schedule ?? 'daily')) {
                $this->line("  Ignoré : {$org->slug} (pas encore l'heure selon la fréquence '{$settings->backup_schedule}')");

                continue;
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
