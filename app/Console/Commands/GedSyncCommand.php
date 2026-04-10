<?php

namespace App\Console\Commands;

use App\Models\Platform\Organization;
use App\Models\Tenant\User;
use App\Services\Ged\GedStorageInterface;
use App\Services\Ged\GedSyncService;
use App\Services\TenantManager;
use Illuminate\Console\Command;

/**
 * Commande Artisan — Synchronisation NAS → GED.
 *
 * Usage :
 *   php artisan ged:sync                       # tous les tenants actifs
 *   php artisan ged:sync --tenant=demo         # un seul tenant
 *   php artisan ged:sync --root=documents/RH   # sous-dossier NAS spécifique
 *
 * Planification (routes/console.php) :
 *   Schedule::command('ged:sync')->hourly();
 *
 * Comportement :
 *   - Scan récursif de l'arborescence NAS GED (nas_ged_* dans tenant_settings)
 *   - Crée automatiquement les GedFolder correspondant aux dossiers NAS
 *   - Les dossiers créés manuellement (nas_path = null) ne sont pas touchés
 *   - Soft-delete les documents/dossiers orphelins (disparus du NAS)
 *   - Signale les fichiers ignorés (MIME non autorisé, taille dépassée)
 */
class GedSyncCommand extends Command
{
    protected $signature = 'ged:sync
                            {--tenant= : Slug du tenant à synchroniser (tous si absent)}
                            {--root=   : Sous-dossier NAS racine à synchroniser (défaut : racine)}';

    protected $description = 'Synchronise l\'arborescence NAS vers la GED (dossiers + documents)';

    public function handle(TenantManager $tenantManager, GedSyncService $syncService): int
    {
        $tenantSlug = $this->option('tenant');
        $nasRoot = (string) ($this->option('root') ?? '');

        $this->info('🔄 Synchronisation GED — NAS → Base de données');
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

        $totalFoldersCreated = 0;
        $totalFoldersFound = 0;
        $totalFoldersRemoved = 0;
        $totalAdded = 0;
        $totalSkipped = 0;
        $totalRemoved = 0;
        $totalErrors = 0;

        foreach ($orgs as $org) {
            $this->line("📁 <info>{$org->name}</info> ({$org->slug})");

            try {
                $tenantManager->connectTo($org);

                $storage = app(GedStorageInterface::class);
                $owner = User::where('role', 'admin')->first();

                $result = $syncService->syncFolderTree($storage, $nasRoot, $owner);

                $removedPart = $result['files_removed'] > 0
                    ? ", <comment>{$result['files_removed']} fichier(s) supprimé(s)</comment>"
                    : '';
                $foldersRemovedPart = $result['folders_removed'] > 0
                    ? ", <comment>{$result['folders_removed']} dossier(s) supprimé(s)</comment>"
                    : '';

                $this->line(sprintf(
                    '   ✓ %d dossier(s) créé(s), %d trouvé(s), %d fichier(s) ajouté(s), %d ignoré(s)%s%s%s',
                    $result['folders_created'],
                    $result['folders_found'],
                    $result['files_added'],
                    $result['files_skipped'],
                    $removedPart,
                    $foldersRemovedPart,
                    $result['errors'] > 0 ? ", <error>{$result['errors']} erreur(s)</error>" : '',
                ));

                if ($result['errors'] > 0 && ! empty($result['error_details'])) {
                    foreach ($result['error_details'] as $detail) {
                        $this->line("      ⚠ {$detail['path']} — {$detail['reason']}");
                    }
                }

                $totalFoldersCreated += $result['folders_created'];
                $totalFoldersFound += $result['folders_found'];
                $totalFoldersRemoved += $result['folders_removed'];
                $totalAdded += $result['files_added'];
                $totalSkipped += $result['files_skipped'];
                $totalRemoved += $result['files_removed'];
                $totalErrors += $result['errors'];

                \App\Models\Tenant\TenantSettings::firstOrNew()->fill([
                    'nas_ged_last_sync_at' => now(),
                    'nas_ged_last_sync_errors' => $result['error_details'],
                ])->save();

            } catch (\Throwable $e) {
                $this->error("   ✗ Erreur tenant {$org->slug} : {$e->getMessage()}");
                $totalErrors++;
            }
        }

        $this->newLine();
        $this->info(sprintf(
            '✅ Sync GED terminée — %d dossier(s) créé(s), %d supprimé(s), %d trouvé(s) · %d fichier(s) ajouté(s), %d supprimé(s), %d ignoré(s) · %d erreur(s).',
            $totalFoldersCreated,
            $totalFoldersRemoved,
            $totalFoldersFound,
            $totalAdded,
            $totalRemoved,
            $totalSkipped,
            $totalErrors,
        ));

        return $totalErrors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
