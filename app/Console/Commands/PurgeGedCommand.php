<?php

namespace App\Console\Commands;

use App\Models\Tenant\GedDocument;
use App\Models\Tenant\GedDocumentVersion;
use App\Models\Tenant\TenantSettings;
use App\Services\Ged\GedStorageInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Purge des données GED expirées.
 *
 * Deux opérations configurables via TenantSettings :
 *
 *   1. Documents soft-deleted — hard-delete définitif après N jours
 *      (ged_deleted_retention_days). Les fichiers des versions archivées
 *      sont supprimés physiquement avant la suppression en base.
 *
 *   2. Versions archivées excédentaires — purge des versions les plus
 *      anciennes au-delà du plafond configuré (ged_versions_max_count).
 *      Le fichier physique de chaque version purgée est supprimé.
 */
class PurgeGedCommand extends Command
{
    protected $signature = 'ged:purge
                            {--dry-run : Afficher ce qui serait supprimé sans agir}';

    protected $description = 'Purge les documents GED supprimés et les versions archivées excédentaires';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $settings = TenantSettings::first();

        if ($settings === null) {
            $this->line('Aucune configuration tenant trouvée — purge ignorée.');

            return self::SUCCESS;
        }

        // GedStorageInterface résolu APRÈS que le tenant est configuré.
        $storage = app(GedStorageInterface::class);

        $totalDocs = 0;
        $totalVersions = 0;

        if ($settings->ged_deleted_retention_days !== null) {
            $totalDocs = $this->purgeDeletedDocuments($storage, $settings->ged_deleted_retention_days, $dry);
        }

        if ($settings->ged_versions_max_count !== null) {
            $totalVersions = $this->purgeExcessVersions($storage, $settings->ged_versions_max_count, $dry);
        }

        if ($dry) {
            $this->info("[dry-run] Documents à purger : {$totalDocs} | Versions à purger : {$totalVersions}");
        } else {
            $this->info("Purge GED terminée — Documents : {$totalDocs} | Versions : {$totalVersions}");
            Log::info('ged:purge — purge terminée', [
                'deleted_docs' => $totalDocs,
                'deleted_versions' => $totalVersions,
            ]);
        }

        return self::SUCCESS;
    }

    /**
     * Hard-delete les documents soft-deleted depuis plus de $days jours.
     * Les fichiers physiques des versions archivées sont supprimés avant.
     */
    private function purgeDeletedDocuments(GedStorageInterface $storage, int $days, bool $dry): int
    {
        $cutoff = now()->subDays($days);

        $query = GedDocument::onlyTrashed()
            ->where('deleted_at', '<=', $cutoff)
            ->with('versions');

        $count = $query->count();

        if ($count === 0) {
            return 0;
        }

        $this->line("Documents soft-deleted depuis >{$days} jours : {$count}");

        if ($dry) {
            return $count;
        }

        $query->each(function (GedDocument $doc) use ($storage): void {
            // Supprimer les fichiers physiques des versions archivées
            foreach ($doc->versions as $version) {
                $this->deletePhysicalFile($storage, $version->disk_path);
            }

            // Hard-delete (cascade sur ged_document_versions)
            $doc->forceDelete();
        });

        return $count;
    }

    /**
     * Purge les versions archivées excédentaires (au-delà de $maxCount).
     * Les versions les plus anciennes (version_number le plus bas) sont supprimées en premier.
     */
    private function purgeExcessVersions(GedStorageInterface $storage, int $maxCount, bool $dry): int
    {
        $total = 0;

        // Récupérer les documents ayant plus de $maxCount versions archivées
        $docIds = GedDocumentVersion::selectRaw('document_id, COUNT(*) as cnt')
            ->groupBy('document_id')
            ->havingRaw('cnt > ?', [$maxCount])
            ->pluck('document_id');

        foreach ($docIds as $docId) {
            $versions = GedDocumentVersion::where('document_id', $docId)
                ->orderBy('version_number', 'desc')
                ->get();

            // Conserver les $maxCount plus récentes, supprimer le reste
            $toDelete = $versions->slice($maxCount);

            if ($toDelete->isEmpty()) {
                continue;
            }

            $total += $toDelete->count();

            $this->line("Document #{$docId} — {$toDelete->count()} version(s) à purger");

            if ($dry) {
                continue;
            }

            foreach ($toDelete as $version) {
                $this->deletePhysicalFile($storage, $version->disk_path);
                $version->delete();
            }
        }

        return $total;
    }

    /**
     * Supprime un fichier physique de manière non bloquante.
     */
    private function deletePhysicalFile(GedStorageInterface $storage, string $path): void
    {
        try {
            $storage->delete($path);
        } catch (\Throwable $e) {
            Log::warning('ged:purge — suppression fichier échouée', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // =========================================================================
    // Stats (pour l'aperçu admin sans exécution)
    // =========================================================================

    /**
     * Retourne les statistiques de ce qui serait purgé, sans rien supprimer.
     *
     * @return array{deleted_docs: int, deleted_docs_size: int, excess_versions: int, excess_versions_size: int}
     */
    public function stats(): array
    {
        $settings = TenantSettings::first();

        $deletedDocs = 0;
        $deletedDocsSize = 0;
        $excessVersions = 0;
        $excessVersionsSize = 0;

        if ($settings !== null && $settings->ged_deleted_retention_days !== null) {
            $cutoff = now()->subDays($settings->ged_deleted_retention_days);

            $docs = GedDocument::onlyTrashed()
                ->where('deleted_at', '<=', $cutoff)
                ->with('versions')
                ->get();

            $deletedDocs = $docs->count();
            $deletedDocsSize = $docs->sum(fn ($d) => $d->versions->sum('size_bytes'));
        }

        if ($settings !== null && $settings->ged_versions_max_count !== null) {
            $maxCount = $settings->ged_versions_max_count;

            $docIds = GedDocumentVersion::selectRaw('document_id, COUNT(*) as cnt')
                ->groupBy('document_id')
                ->havingRaw('cnt > ?', [$maxCount])
                ->pluck('document_id');

            foreach ($docIds as $docId) {
                $toDelete = GedDocumentVersion::where('document_id', $docId)
                    ->orderBy('version_number', 'desc')
                    ->skip($maxCount)
                    ->get();

                $excessVersions += $toDelete->count();
                $excessVersionsSize += $toDelete->sum('size_bytes');
            }
        }

        return [
            'deleted_docs' => $deletedDocs,
            'deleted_docs_size' => $deletedDocsSize,
            'excess_versions' => $excessVersions,
            'excess_versions_size' => $excessVersionsSize,
        ];
    }
}
