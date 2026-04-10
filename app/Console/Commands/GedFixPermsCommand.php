<?php

namespace App\Console\Commands;

use App\Models\Platform\Organization;
use App\Models\Tenant\TenantSettings;
use App\Services\TenantManager;
use Illuminate\Console\Command;

/**
 * Commande Artisan — Correction des droits sur les fichiers GED locaux.
 *
 * Corrige les permissions des fichiers/dossiers copiés par un autre utilisateur
 * OS qui seraient illisibles par le process web.
 *
 * Usage :
 *   php artisan ged:fix-perms                  # tous les tenants actifs
 *   php artisan ged:fix-perms --tenant=demo    # un seul tenant
 *
 * Permissions appliquées :
 *   - Fichiers  : a+r   (lecture pour tous)
 *   - Dossiers  : a+rX  (lecture + traversée pour tous)
 *
 * Note : ne fonctionne que si le process PHP est propriétaire des fichiers
 * OU s'il dispose de CAP_FOWNER (ex: sudo). Pour les fichiers appartenant
 * à un autre utilisateur, exécuter avec sudo ou configurer setfacl.
 */
class GedFixPermsCommand extends Command
{
    protected $signature = 'ged:fix-perms
                            {--tenant= : Slug du tenant à traiter (tous si absent)}
                            {--dry-run : Affiche les fichiers à corriger sans les modifier}';

    protected $description = 'Corrige les droits (chmod a+r) sur les fichiers GED locaux illisibles';

    public function handle(TenantManager $tenantManager): int
    {
        $tenantSlug = $this->option('tenant');
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Mode dry-run — aucune modification ne sera appliquée.');
        }

        $this->info('🔑 Correction des droits GED');
        $this->newLine();

        $orgs = $tenantSlug
            ? Organization::where('slug', $tenantSlug)->where('status', 'active')->get()
            : Organization::where('status', 'active')->get();

        if ($orgs->isEmpty()) {
            $this->warn('Aucune organisation active trouvée.');

            return self::SUCCESS;
        }

        $totalFixed = 0;
        $totalSkipped = 0;
        $totalErrors = 0;

        foreach ($orgs as $org) {
            $this->line("📁 <info>{$org->name}</info> ({$org->slug})");

            try {
                $tenantManager->connectTo($org);
                $settings = TenantSettings::firstOrNew();

                // Ne traite que le driver local (SFTP/SMB gèrent leurs propres droits)
                $driver = $settings->nas_ged_driver ?? 'local';
                if ($driver !== 'local') {
                    $this->line("   ⏭ Driver <comment>{$driver}</comment> — correction non applicable (NAS réseau).");

                    continue;
                }

                $nasPath = $settings->nas_ged_local_path ?? '';
                if ($nasPath === '' || ! is_dir($nasPath)) {
                    $this->warn("   ⚠ Chemin NAS non configuré ou introuvable : « {$nasPath} »");

                    continue;
                }

                [$fixed, $skipped, $errors] = $this->fixDirectory($nasPath, $dryRun);

                $this->line(sprintf(
                    '   ✓ %d corrigé(s), %d déjà OK, %d échec(s) (sudo requis)',
                    $fixed,
                    $skipped,
                    $errors,
                ));

                if ($errors > 0) {
                    $this->warn('   ⚠ Pour les fichiers non corrigés, exécutez :');
                    $this->line('     <comment>sudo find '.escapeshellarg($nasPath).' -not -perm -a+r -exec chmod a+r {} \\;</comment>');
                    $this->line('     <comment>sudo find '.escapeshellarg($nasPath).' -type d -not -perm -a+rx -exec chmod a+rx {} \\;</comment>');
                }

                $totalFixed += $fixed;
                $totalSkipped += $skipped;
                $totalErrors += $errors;

            } catch (\Throwable $e) {
                $this->error("   ✗ Erreur tenant {$org->slug} : {$e->getMessage()}");
                $totalErrors++;
            }
        }

        $this->newLine();
        $this->info(sprintf(
            '✅ Terminé — %d fichier(s)/dossier(s) corrigé(s), %d déjà OK, %d échec(s).',
            $totalFixed,
            $totalSkipped,
            $totalErrors,
        ));

        return $totalErrors > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Parcourt récursivement un répertoire et corrige les permissions.
     * Récursion manuelle pour isoler les dossiers inaccessibles sans planter.
     *
     * @return array{int, int, int} [fixed, skipped, errors]
     */
    private function fixDirectory(string $path, bool $dryRun): array
    {
        $fixed = 0;
        $skipped = 0;
        $errors = 0;

        $this->fixEntry($path, $dryRun, $fixed, $skipped, $errors);

        return [$fixed, $skipped, $errors];
    }

    /**
     * Applique le chmod sur une entrée puis descend dans les sous-dossiers.
     */
    private function fixEntry(string $fullPath, bool $dryRun, int &$fixed, int &$skipped, int &$errors): void
    {
        $isDir = is_dir($fullPath);
        $targetMode = $isDir ? 0755 : 0644;

        $currentPerms = @fileperms($fullPath);
        if ($currentPerms === false) {
            $errors++;

            return;
        }
        $currentPerms &= 0777;

        $needsRead = ($currentPerms & 0004) === 0;
        $needsExec = $isDir && ($currentPerms & 0001) === 0;

        if ($needsRead || $needsExec) {
            if ($dryRun) {
                $this->line(sprintf('   [dry] %s %s → %04o', $isDir ? '📁' : '📄', $fullPath, $targetMode));
                $fixed++;
            } elseif (@chmod($fullPath, $targetMode)) {
                $fixed++;
            } else {
                $errors++;
            }
        } else {
            $skipped++;
        }

        // Descente dans les sous-dossiers — isolée par try/catch pour ne pas planter
        // si le dossier n'est pas accessible (et qu'on vient de réussir le chmod dessus).
        if ($isDir) {
            try {
                $items = new \FilesystemIterator($fullPath, \FilesystemIterator::SKIP_DOTS);
                foreach ($items as $item) {
                    $this->fixEntry($item->getRealPath() ?: $item->getPathname(), $dryRun, $fixed, $skipped, $errors);
                }
            } catch (\Throwable) {
                $errors++;
            }
        }
    }
}
