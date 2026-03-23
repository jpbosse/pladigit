<?php

namespace App\Console\Commands;

use App\Models\Platform\Organization;
use App\Services\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Détecte et marque les doublons SHA-256 dans tous les albums d'un tenant.
 *
 * Usage :
 *   php artisan media:deduplicate                  — tous les tenants actifs
 *   php artisan media:deduplicate --slug=cedbos    — un seul tenant
 *   php artisan media:deduplicate --dry-run        — aperçu sans modifier
 */
class DeduplicateMediaCommand extends Command
{
    protected $signature = 'media:deduplicate
                            {--slug= : Slug d\'une organisation spécifique}
                            {--dry-run : Afficher les doublons sans modifier la base}';

    protected $description = 'Détecte et marque les doublons SHA-256 dans la photothèque (sync NAS incluse)';

    public function __construct(private TenantManager $tenantManager)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $query = Organization::query();

        if ($slug = $this->option('slug')) {
            $query->where('slug', $slug);
        } else {
            $query->where('status', 'active');
        }

        $organizations = $query->get();

        if ($organizations->isEmpty()) {
            $this->warn('Aucune organisation trouvée.');

            return self::SUCCESS;
        }

        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->line('<fg=yellow>Mode dry-run — aucune modification ne sera effectuée.</>');
            $this->newLine();
        }

        $totalMarked = 0;
        $totalGroups = 0;

        foreach ($organizations as $org) {
            $this->line("  → <fg=cyan>{$org->slug}</> (<fg=gray>{$org->db_name}</>)");

            try {
                $this->tenantManager->connectTo($org);

                [$groups, $marked] = $this->processTenant($dryRun);

                $totalGroups += $groups;
                $totalMarked += $marked;

                $this->line("     <fg=green>✓</> {$groups} groupe(s) de doublons — {$marked} item(s) marqué(s)");

            } catch (\Throwable $e) {
                $this->line("     <fg=red>✗ Erreur : {$e->getMessage()}</>");
            } finally {
                DB::purge('tenant');
            }

            $this->newLine();
        }

        $this->line('─────────────────────────────────────────');
        $this->line("  <fg=green>{$totalGroups} groupe(s) de doublons</> — <fg=yellow>{$totalMarked} item(s) marqué(s)</>");

        if ($dryRun) {
            $this->line('  <fg=yellow>Dry-run : aucune modification effectuée.</>');
        }

        return self::SUCCESS;
    }

    /**
     * Traite un tenant : trouve les SHA-256 dupliqués et marque les items.
     *
     * @return array{int, int} [nombre de groupes, nombre d'items marqués]
     */
    private function processTenant(bool $dryRun): array
    {
        // Trouver tous les SHA-256 qui apparaissent plus d'une fois
        $duplicateHashes = DB::connection('tenant')
            ->table('media_items')
            ->whereNotNull('sha256_hash')
            ->whereNull('deleted_at')
            ->select('sha256_hash', DB::raw('COUNT(*) as cnt'))
            ->groupBy('sha256_hash')
            ->having('cnt', '>', 1)
            ->pluck('cnt', 'sha256_hash');

        if ($duplicateHashes->isEmpty()) {
            return [0, 0];
        }

        $groups = $duplicateHashes->count();
        $marked = 0;

        foreach ($duplicateHashes as $hash => $count) {
            $items = DB::connection('tenant')
                ->table('media_items')
                ->whereNull('deleted_at')
                ->where('sha256_hash', $hash)
                ->get(['id', 'file_name', 'album_id', 'is_duplicate']);

            $this->line('     SHA-256 <fg=gray>'.substr($hash, 0, 12)."…</> × {$count} :");

            foreach ($items as $item) {
                $alreadyMarked = (bool) $item->is_duplicate;
                $this->line("       - [{$item->id}] {$item->file_name} (album {$item->album_id})"
                    .($alreadyMarked ? ' <fg=gray>[déjà marqué]</>' : ' <fg=yellow>[à marquer]</>'));

                if (! $alreadyMarked) {
                    $marked++;
                }
            }

            if (! $dryRun) {
                DB::connection('tenant')
                    ->table('media_items')
                    ->whereNull('deleted_at')
                    ->where('sha256_hash', $hash)
                    ->update(['is_duplicate' => true]);
            }
        }

        // Nettoyer les faux positifs : items marqués is_duplicate mais dont le SHA
        // n'apparaît plus qu'une fois (ex: l'original a été supprimé)
        if (! $dryRun) {
            $singleHashes = DB::connection('tenant')
                ->table('media_items')
                ->whereNotNull('sha256_hash')
                ->whereNull('deleted_at')
                ->where('is_duplicate', true)
                ->select('sha256_hash', DB::raw('COUNT(*) as cnt'))
                ->groupBy('sha256_hash')
                ->having('cnt', '=', 1)
                ->pluck('sha256_hash');

            if ($singleHashes->isNotEmpty()) {
                $cleaned = DB::connection('tenant')
                    ->table('media_items')
                    ->whereNull('deleted_at')
                    ->whereIn('sha256_hash', $singleHashes)
                    ->where('is_duplicate', true)
                    ->update(['is_duplicate' => false]);

                if ($cleaned > 0) {
                    $this->line("     <fg=green>↺ {$cleaned} faux positif(s) nettoyé(s)</>");
                }
            }
        }

        return [$groups, $marked];
    }
}
