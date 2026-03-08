<?php

namespace App\Console\Commands;

use App\Models\Platform\Organization;
use App\Models\Tenant\MediaAlbum;
use App\Models\Tenant\TenantSettings;
use App\Services\MediaService;
use App\Services\TenantManager;
use Illuminate\Console\Command;

/**
 * Commande Artisan — Synchronisation NAS → BDD.
 *
 * Usage :
 *   php artisan nas:sync                        # tous les tenants, sync légère
 *   php artisan nas:sync --tenant=demo          # un seul tenant
 *   php artisan nas:sync --deep                 # sync complète SHA-256
 *   php artisan nas:sync --tenant=demo --deep   # un tenant, sync complète
 *
 * Planification (app/Console/Kernel.php) :
 *   $schedule->command('nas:sync')->hourly();
 *   $schedule->command('nas:sync --deep')->dailyAt('23:30');
 */
class NasSyncCommand extends Command
{
    protected $signature = 'nas:sync
                            {--tenant= : Slug du tenant à synchroniser (tous si absent)}
                            {--deep    : Sync complète par SHA-256 (plus lente)}';

    protected $description = 'Synchronise les fichiers NAS vers la base de données (photothèque)';

    public function handle(TenantManager $tenantManager, MediaService $mediaService): int
    {
        $deep = (bool) $this->option('deep');
        $tenantSlug = $this->option('tenant');

        $this->info('🔄 Synchronisation NAS — mode '.($deep ? 'SHA-256 (complète)' : 'mtime (légère)'));
        $this->newLine();

        $orgs = $tenantSlug
            ? Organization::where('slug', $tenantSlug)->where('status', 'active')->get()
            : Organization::where('status', 'active')->get();

        if ($orgs->isEmpty()) {
            $this->warn('Aucune organisation active trouvée.');

            return self::SUCCESS;
        }

        $totalAdded = 0;
        $totalSkipped = 0;
        $totalErrors = 0;

        foreach ($orgs as $org) {
            $this->line("📁 <info>{$org->name}</info> ({$org->slug})");

            try {
                $tenantManager->connectTo($org);

                $settings = TenantSettings::first();

                $driver = $settings !== null ? $settings->nas_photo_driver ?? 'local' : 'local';

                /*                if ($driver === 'local' && ! app()->environment('local', 'testing')) {
                                    $this->line("   ⏭  Driver local ignoré en production.");
                                    continue;
                                }
                */
                $albums = MediaAlbum::all();

                if ($albums->isEmpty()) {
                    $this->line('   ℹ  Aucun album — synchronisation ignorée.');

                    continue;
                }

                foreach ($albums as $album) {
                    $nasDir = "albums/{$album->id}";

                    try {
                        if ($deep) {
                            $result = $mediaService->syncBySha256($album, $nasDir);
                            $this->line("   ✓ Album « {$album->name} » — {$result['updated']} modifié(s), {$result['unchanged']} inchangé(s)");
                            $totalAdded += $result['updated'];
                        } else {
                            $result = $mediaService->syncByMtime($album, $nasDir);
                            $this->line("   ✓ Album « {$album->name} » — {$result['added']} ajouté(s), {$result['skipped']} ignoré(s)");
                            $totalAdded += $result['added'];
                            $totalSkipped += $result['skipped'];
                        }
                    } catch (\Throwable $e) {
                        $this->error("   ✗ Album « {$album->name} » — {$e->getMessage()}");
                        $totalErrors++;
                    }
                }

                // Mise à jour de la dernière sync
                TenantSettings::first()?->update(['nas_photo_last_sync_at' => now()]);

            } catch (\Throwable $e) {
                $this->error("   ✗ Erreur tenant {$org->slug} : {$e->getMessage()}");
                $totalErrors++;
            }
        }

        $this->newLine();
        $this->info("✅ Sync terminée — {$totalAdded} ajouté(s), {$totalSkipped} ignoré(s), {$totalErrors} erreur(s).");

        return $totalErrors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
