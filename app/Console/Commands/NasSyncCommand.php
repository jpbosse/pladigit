<?php

namespace App\Console\Commands;

use App\Models\Platform\Organization;
use App\Models\Tenant\TenantSettings;
use App\Models\Tenant\User;
use App\Services\MediaService;
use App\Services\TenantManager;
use Illuminate\Console\Command;

/**
 * Commande Artisan — Synchronisation NAS → BDD.
 *
 * Usage :
 *   php artisan nas:sync                        # tous les tenants, sync légère (mtime)
 *   php artisan nas:sync --tenant=demo          # un seul tenant
 *   php artisan nas:sync --deep                 # sync complète SHA-256
 *   php artisan nas:sync --tenant=demo --deep   # un tenant, sync complète
 *   php artisan nas:sync --root=photos/2026     # sous-dossier NAS spécifique
 *
 * Planification (app/Console/Kernel.php) :
 *   $schedule->command('nas:sync')->hourly();
 *   $schedule->command('nas:sync --deep')->dailyAt('23:30');
 *
 * Comportement :
 *   - Parcourt récursivement l'arborescence NAS
 *   - Crée automatiquement les albums et sous-albums correspondant aux dossiers
 *   - Les albums déjà liés à un chemin NAS (nas_path) sont réutilisés sans recréation
 *   - Les albums créés manuellement (nas_path = null) ne sont pas touchés
 */
class NasSyncCommand extends Command
{
    protected $signature = 'nas:sync
                            {--tenant= : Slug du tenant à synchroniser (tous si absent)}
                            {--deep    : Sync complète par SHA-256 (plus lente)}
                            {--root=   : Sous-dossier NAS racine à synchroniser (défaut : racine)}';

    protected $description = 'Synchronise l\'arborescence NAS vers la base de données (albums + médias)';

    public function handle(TenantManager $tenantManager, MediaService $mediaService): int
    {
        $deep = (bool) $this->option('deep');
        $tenantSlug = $this->option('tenant');
        $nasRoot = (string) ($this->option('root') ?? '');

        $this->info('🔄 Synchronisation NAS — mode '.($deep ? 'SHA-256 (complète)' : 'mtime (légère)'));
        if ($nasRoot !== '') {
            $this->line("   Racine NAS : {$nasRoot}");
        }
        $this->newLine();

        $orgs = $tenantSlug
            ? Organization::where('slug', $tenantSlug)->where('status', 'active')->get()
            : Organization::where('status', 'active')->get();

        if ($orgs->isEmpty()) {
            $this->warn('Aucune organisation active trouvée.');

            return self::SUCCESS;
        }

        $totalCreated = 0;
        $totalFound = 0;
        $totalAdded = 0;
        $totalSkipped = 0;
        $totalErrors = 0;

        foreach ($orgs as $org) {
            $this->line("📁 <info>{$org->name}</info> ({$org->slug})");

            try {
                $tenantManager->connectTo($org);

                $settings = TenantSettings::first();
                $driver = $settings !== null ? $settings->nas_photo_driver : 'local';

                // Premier admin du tenant comme propriétaire des albums créés automatiquement
                $owner = User::where('role', 'admin')->first();

                $result = $mediaService->syncAlbumTree(
                    nasRoot: $nasRoot,
                    owner: $owner,
                    deep: $deep,
                );

                $this->line(sprintf(
                    '   ✓ %d album(s) créé(s), %d trouvé(s), %d fichier(s) ajouté(s), %d ignoré(s)%s',
                    $result['albums_created'],
                    $result['albums_found'],
                    $result['files_added'],
                    $result['files_skipped'],
                    $result['errors'] > 0 ? ", <error>{$result['errors']} erreur(s)</error>" : '',
                ));

                $totalCreated += $result['albums_created'];
                $totalFound += $result['albums_found'];
                $totalAdded += $result['files_added'];
                $totalSkipped += $result['files_skipped'];
                $totalErrors += $result['errors'];

                // Mise à jour de la dernière sync
                $settings?->update(['nas_photo_last_sync_at' => now()]);

            } catch (\Throwable $e) {
                $this->error("   ✗ Erreur tenant {$org->slug} : {$e->getMessage()}");
                $totalErrors++;
            }
        }

        $this->newLine();
        $this->info(sprintf(
            '✅ Sync terminée — %d album(s) créé(s), %d trouvé(s), %d fichier(s) ajouté(s), %d ignoré(s), %d erreur(s).',
            $totalCreated,
            $totalFound,
            $totalAdded,
            $totalSkipped,
            $totalErrors,
        ));

        return $totalErrors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
