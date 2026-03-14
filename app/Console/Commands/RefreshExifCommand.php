<?php

// app/Console/Commands/RefreshExifCommand.php

namespace App\Console\Commands;

use App\Models\Platform\Organization;
use App\Services\MediaService;
use App\Services\Nas\NasManager;
use App\Services\TenantManager;
use Illuminate\Console\Command;

/**
 * Commande Artisan — Re-extraction EXIF sur les MediaItems existants.
 *
 * Usage :
 *   php artisan media:refresh-exif                         # tous les tenants, items sans EXIF
 *   php artisan media:refresh-exif --tenant=demo           # un seul tenant
 *   php artisan media:refresh-exif --force                 # ré-extraire même si exif_data déjà rempli
 *   php artisan media:refresh-exif --tenant=demo --force   # combiné
 *
 * Cas d'usage typique :
 *   Les fichiers ingérés via syncByMtime (ingestNasFile) ont exif_data = null
 *   car la sync légère ne lit pas le contenu du fichier.
 *   Cette commande relit chaque fichier depuis le NAS pour extraire les métadonnées.
 *
 * Planification optionnelle (app/Console/Kernel.php) :
 *   $schedule->command('media:refresh-exif')->weeklyOn(0, '02:00');
 */
class RefreshExifCommand extends Command
{
    protected $signature = 'media:refresh-exif
                            {--tenant= : Slug du tenant à traiter (tous si absent)}
                            {--force   : Ré-extraire même si exif_data est déjà renseigné}';

    protected $description = 'Re-extrait les métadonnées EXIF des photos depuis le NAS';

    public function handle(
        TenantManager $tenantManager,
        MediaService $mediaService,
        NasManager $nasManager,
    ): int {
        $force = (bool) $this->option('force');
        $tenantSlug = $this->option('tenant');

        $this->info('🔍 Re-extraction EXIF — mode '.($force ? 'forcé (tous les items)' : 'items sans EXIF uniquement'));
        $this->newLine();

        $orgs = $tenantSlug
            ? Organization::where('slug', $tenantSlug)->where('status', 'active')->get()
            : Organization::where('status', 'active')->get();

        if ($orgs->isEmpty()) {
            $tenantSlug
                ? $this->error("Tenant « {$tenantSlug} » introuvable ou inactif.")
                : $this->warn('Aucune organisation active trouvée.');

            return self::FAILURE;
        }

        $grandTotal = ['updated' => 0, 'skipped' => 0, 'errors' => 0];

        foreach ($orgs as $org) {
            $this->line("📁 <info>{$org->name}</info> ({$org->slug})");

            try {
                $tenantManager->connectTo($org);

                $result = $mediaService->refreshExif(
                    nas: $nasManager->photoDriver(),
                    force: $force,
                    output: fn (string $msg) => $this->line("   {$msg}"),
                );

                $this->line(sprintf(
                    '   ✓ %d mis à jour, %d ignorés%s',
                    $result['updated'],
                    $result['skipped'],
                    $result['errors'] > 0 ? ", <error>{$result['errors']} erreur(s)</error>" : '',
                ));

                $grandTotal['updated'] += $result['updated'];
                $grandTotal['skipped'] += $result['skipped'];
                $grandTotal['errors'] += $result['errors'];

            } catch (\Throwable $e) {
                $this->error("   ✗ Erreur tenant {$org->slug} : {$e->getMessage()}");
                $grandTotal['errors']++;
            }
        }

        $this->newLine();
        $this->info(sprintf(
            '✅ Terminé — %d item(s) mis à jour, %d ignoré(s), %d erreur(s).',
            $grandTotal['updated'],
            $grandTotal['skipped'],
            $grandTotal['errors'],
        ));

        return $grandTotal['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
