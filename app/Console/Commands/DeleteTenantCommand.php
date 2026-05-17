<?php

namespace App\Console\Commands;

use App\Models\Platform\Organization;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Suppression complète d'un tenant — base MySQL, fichiers GED, sauvegardes locales.
 *
 * Usage :
 *   php artisan pladigit:delete-tenant --slug=demo
 *   php artisan pladigit:delete-tenant --slug=demo --force   (sans confirmation interactive)
 *
 * RGPD — droit à l'effacement (article 17) :
 *   Toutes les données du tenant sont supprimées de manière irréversible.
 *   Un log de traçabilité est écrit dans laravel.log.
 */
class DeleteTenantCommand extends Command
{
    protected $signature = 'pladigit:delete-tenant
                            {--slug= : Slug de l\'organisation à supprimer}
                            {--force : Ignorer la confirmation interactive}';

    protected $description = 'Supprime définitivement un tenant : base MySQL, GED et sauvegardes locales (RGPD).';

    public function handle(): int
    {
        $slug = $this->option('slug');

        if (empty($slug)) {
            $this->error('Le paramètre --slug est obligatoire.');
            $this->line('  Exemple : php artisan pladigit:delete-tenant --slug=demo');

            return self::FAILURE;
        }

        // Trouver l'organisation
        $org = Organization::where('slug', $slug)->first();

        if (! $org) {
            $this->error("Aucune organisation trouvée avec le slug « {$slug} ».");

            return self::FAILURE;
        }

        // Afficher un résumé de ce qui va être supprimé
        $this->newLine();
        $this->line('  <fg=red;options=bold>⚠  SUPPRESSION DÉFINITIVE ET IRRÉVERSIBLE</>');
        $this->newLine();
        $this->table(
            ['Élément', 'Valeur'],
            [
                ['Organisation', $org->name],
                ['Slug', $org->slug],
                ['Base MySQL', $org->db_name],
                ['GED', storage_path('app/private/ged/organisations/'.$slug)],
                ['Sauvegardes locales', storage_path('app/private/backups/'.$slug).' + /var/backups/pladigit/'.$slug],
                ['Statut actuel', $org->status],
            ]
        );
        $this->newLine();

        // Confirmation interactive
        if (! $this->option('force')) {
            $confirm = $this->ask("Pour confirmer, saisissez le slug « {$slug} »");

            if ($confirm !== $slug) {
                $this->warn('Suppression annulée — le slug saisi ne correspond pas.');

                return self::FAILURE;
            }

            if (! $this->confirm('Dernière confirmation : supprimer définitivement toutes les données ?', false)) {
                $this->warn('Suppression annulée.');

                return self::FAILURE;
            }
        }

        $errors = [];

        // ── 1. Base MySQL ─────────────────────────────────────────────────────
        $this->line('  Suppression de la base MySQL...');
        try {
            $dbName = str_replace('`', '', $org->db_name);
            DB::statement("DROP DATABASE IF EXISTS `{$dbName}`");
            $this->line('  <fg=green>✓</> Base MySQL supprimée.');
        } catch (\Throwable $e) {
            $errors[] = 'Base MySQL : '.$e->getMessage();
            $this->error('  ✗ Base MySQL : '.$e->getMessage());
        }

        // ── 2. Fichiers GED ───────────────────────────────────────────────────
        $gedPath = storage_path('app/private/ged/organisations/'.trim($slug, '/'));
        $this->line('  Suppression des fichiers GED...');
        if (is_dir($gedPath)) {
            try {
                $this->deleteDirectory($gedPath);
                $this->line('  <fg=green>✓</> GED supprimée.');
            } catch (\Throwable $e) {
                $errors[] = 'GED : '.$e->getMessage();
                $this->error('  ✗ GED : '.$e->getMessage());
            }
        } else {
            $this->line('  <fg=gray>- GED : répertoire absent (ignoré).</>');
        }

        // ── 3. Sauvegardes locales ────────────────────────────────────────────
        $backupPaths = [
            storage_path('app/private/backups/'.$slug),
            '/var/backups/pladigit/'.$slug,
        ];

        $this->line('  Suppression des sauvegardes locales...');
        foreach ($backupPaths as $backupPath) {
            if (is_dir($backupPath)) {
                try {
                    $this->deleteDirectory($backupPath);
                    $this->line("  <fg=green>✓</> Sauvegardes supprimées : {$backupPath}");
                } catch (\Throwable $e) {
                    $errors[] = 'Sauvegardes : '.$e->getMessage();
                    $this->error("  ✗ Sauvegardes [{$backupPath}] : ".$e->getMessage());
                }
            } else {
                $this->line("  <fg=gray>- Sauvegardes : {$backupPath} absent (ignoré).</>");
            }
        }

        // ── 4. Log RGPD ───────────────────────────────────────────────────────
        Log::info("Tenant [{$slug}] supprimé définitivement via artisan (RGPD).", [
            'org_name' => $org->name,
            'db_name' => $org->db_name,
            'ged_path' => $gedPath,
            'errors' => $errors,
            'deleted_by' => 'artisan pladigit:delete-tenant',
            'deleted_at' => now()->toIso8601String(),
        ]);

        // ── 5. Suppression definitive en base (force delete) ──────────────────
        $this->line('  Suppression de l\'entrée en base...');
        try {
            $org->delete();
            $this->line('  <fg=green>✓</> Entrée supprimée définitivement.');
        } catch (\Throwable $e) {
            $errors[] = 'Base platform : '.$e->getMessage();
            $this->error('  ✗ Base platform : '.$e->getMessage());
        }

        // ── Résultat ──────────────────────────────────────────────────────────
        $this->newLine();
        if (empty($errors)) {
            $this->info("✅  Organisation « {$org->name} » supprimée définitivement.");
        } else {
            $this->warn('⚠  Suppression terminée avec '.count($errors).' avertissement(s). Vérifiez les logs.');
        }
        $this->newLine();

        return empty($errors) ? self::SUCCESS : self::FAILURE;
    }

    private function deleteDirectory(string $path): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getRealPath()) : unlink($item->getRealPath());
        }

        rmdir($path);
    }
}
