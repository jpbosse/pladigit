<?php

namespace App\Services\Ged;

use App\Models\Tenant\GedDocument;
use App\Models\Tenant\GedDocumentVersion;
use App\Models\Tenant\GedFolder;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Service de synchronisation NAS → GED.
 *
 * Scan récursif du NAS (arborescence infinie) :
 *   - Crée les GedFolder manquants (correspondance par nas_path)
 *   - Crée les GedDocument manquants (correspondance par disk_path)
 *   - Soft-delete les documents dont le fichier a disparu
 *   - Soft-delete les dossiers vides dont le répertoire a disparu
 *   - Remonte les fichiers ignorés (MIME interdit, taille dépassée)
 *
 * Fichiers créés manuellement (nas_path = null) ne sont jamais touchés.
 */
class GedSyncService
{
    /** Types MIME autorisés (même liste que config/ged.php). */
    private array $allowedMimes;

    /** Taille max en octets. */
    private int $maxFileSize;

    public function __construct()
    {
        $this->allowedMimes = config('ged.allowed_mimes', []);
        $this->maxFileSize = (int) config('ged.max_file_size', 50 * 1024 * 1024);
    }

    // =========================================================================
    // Point d'entrée public
    // =========================================================================

    /**
     * Lance la synchronisation complète depuis la racine GED storage.
     *
     * @param  GedStorageInterface  $storage  Driver GED configuré pour le tenant
     * @param  string  $nasRoot  Sous-dossier de départ ('' = racine)
     * @param  User|null  $owner  Propriétaire des dossiers/documents créés
     * @return array{
     *   folders_created: int, folders_found: int, folders_removed: int,
     *   files_added: int, files_skipped: int, files_removed: int,
     *   errors: int, error_details: list<array{path: string, reason: string}>
     * }
     */
    public function syncFolderTree(GedStorageInterface $storage, string $nasRoot = '', ?User $owner = null): array
    {
        $lockKey = 'ged_sync_lock_'.md5(config('database.connections.tenant.database', 'tenant'));
        $lock = Cache::lock($lockKey, 600); // TTL 10 min

        if (! $lock->get()) {
            Log::warning('GED sync déjà en cours, abandon.');

            return $this->emptyStats(['skipped_reason' => 'lock']);
        }

        try {
            return $this->doSync($storage, $nasRoot, $owner);
        } finally {
            $lock->release();
        }
    }

    // =========================================================================
    // Implémentation interne
    // =========================================================================

    /** @return array<string, mixed> */
    private function doSync(GedStorageInterface $storage, string $nasRoot, ?User $owner): array
    {
        $stats = $this->emptyStats();

        // Récupérer le propriétaire par défaut (admin du tenant)
        $owner ??= User::where('role', 'admin')->first();

        // Scanner les entrées de la racine
        try {
            $topEntries = $storage->listDirectory($nasRoot);
        } catch (\Throwable $e) {
            Log::error('GED sync — impossible de lister la racine NAS', ['error' => $e->getMessage()]);
            $stats['errors']++;
            $stats['error_details'][] = ['path' => $nasRoot ?: '/', 'reason' => 'Racine inaccessible : '.$e->getMessage()];

            return $stats;
        }

        foreach ($topEntries as $entry) {
            if ($entry['type'] === 'dir') {
                $this->syncDirectory($storage, $entry['path'], null, $owner, $stats);
            }
        }

        // Purger les documents orphelins (fichier disparu du stockage)
        $this->purgeOrphanDocuments($storage, $stats);

        // Purger les dossiers orphelins vides (répertoire disparu du stockage)
        $this->purgeOrphanFolders($storage, $stats);

        return $stats;
    }

    /**
     * Synchronise récursivement un répertoire vers un GedFolder.
     *
     * @param  array<string, mixed>  $stats
     */
    private function syncDirectory(
        GedStorageInterface $storage,
        string $nasPath,
        ?int $parentId,
        ?User $owner,
        array &$stats,
    ): void {
        // 1. Trouver ou créer le dossier GED correspondant
        $folder = $this->findOrCreateFolder($nasPath, $parentId, $owner, $stats);
        if ($folder === null) {
            return;
        }

        // 2. Synchroniser les fichiers et descendre dans les sous-répertoires
        try {
            $entries = $storage->listDirectory($nasPath);
        } catch (\Throwable $e) {
            Log::warning('GED sync — impossible de lister le dossier', [
                'nas_path' => $nasPath,
                'error' => $e->getMessage(),
            ]);
            $stats['errors']++;
            $stats['error_details'][] = ['path' => $nasPath, 'reason' => 'Lecture impossible : '.$e->getMessage()];

            return;
        }

        foreach ($entries as $entry) {
            if ($entry['type'] === 'file') {
                $this->processFile($entry, $folder, $stats);
            } elseif ($entry['type'] === 'dir') {
                $this->syncDirectory($storage, $entry['path'], $folder->id, $owner, $stats);
            }
        }
    }

    /**
     * Trouve un GedFolder existant (par nas_path) ou en crée un nouveau.
     *
     * @param  array<string, mixed>  $stats
     */
    private function findOrCreateFolder(
        string $nasPath,
        ?int $parentId,
        ?User $owner,
        array &$stats,
    ): ?GedFolder {
        // Cherche par nas_path + parent (même nas_path peut exister sous différents parents)
        $existing = GedFolder::withTrashed()
            ->where('nas_path', $nasPath)
            ->where('parent_id', $parentId)
            ->first();

        // Fallback : dossier manuel (nas_path = null) avec le même nom → on l'adopte
        if ($existing === null) {
            $existing = GedFolder::withTrashed()
                ->whereNull('nas_path')
                ->where('name', basename($nasPath))
                ->where('parent_id', $parentId)
                ->first();

            if ($existing !== null) {
                if ($existing->trashed()) {
                    $existing->restore();
                }
                $existing->nas_path = $nasPath;
                $existing->save();
                $stats['folders_found']++;

                return $existing;
            }
        }

        if ($existing !== null) {
            // Restaurer si soft-deleted
            if ($existing->trashed()) {
                $existing->restore();
            }

            $stats['folders_found']++;

            return $existing;
        }

        // Créer le dossier
        $name = basename($nasPath);
        $ownerId = $owner !== null ? $owner->id : (User::where('role', 'admin')->value('id') ?? 1);
        $slug = GedFolder::uniqueSlug($name, $parentId);
        $path = $this->computePath($slug, $parentId);

        try {
            $folder = GedFolder::create([
                'name' => $name,
                'slug' => $slug,
                'path' => $path,
                'nas_path' => $nasPath,
                'parent_id' => $parentId,
                'is_private' => false,
                'created_by' => $ownerId,
            ]);

            $stats['folders_created']++;
            Log::info('GED sync — dossier créé', ['nas_path' => $nasPath, 'path' => $path]);

            return $folder;

        } catch (\Throwable $e) {
            Log::error('GED sync — erreur création dossier', [
                'nas_path' => $nasPath,
                'error' => $e->getMessage(),
            ]);
            $stats['errors']++;

            return null;
        }
    }

    /**
     * Traite un fichier NAS : validation, ingestion ou rapport d'erreur.
     *
     * @param  array{name: string, path: string, size: int, mtime: int, type: string}  $entry
     * @param  array<string, mixed>  $stats
     */
    private function processFile(array $entry, GedFolder $folder, array &$stats): void
    {
        $diskPath = $entry['path'];

        // ── Document déjà connu (même disk_path dans ce dossier) ──────────
        $existing = GedDocument::withTrashed()
            ->where('folder_id', $folder->id)
            ->where('disk_path', $diskPath)
            ->first();

        if ($existing !== null) {
            if ($existing->trashed()) {
                $existing->restore();
                $stats['files_added']++;
            } else {
                $stats['files_skipped']++;
            }

            return;
        }

        // ── Fichier appartenant à une version archivée → ignorer ──────────
        // Les versions archivées (ged_document_versions) ont leurs propres
        // disk_path sur le NAS. Sans cette vérification, le sync les recréerait
        // comme de nouveaux documents indépendants.
        if (GedDocumentVersion::where('disk_path', $diskPath)->exists()) {
            $stats['files_skipped']++;

            return;
        }

        // ── Vérification des droits de lecture ────────────────────────────
        // Fichiers copiés par un autre utilisateur OS avec des permissions
        // trop restrictives (ex: 600/700). Signaler clairement, ne pas planter.
        if (isset($entry['readable']) && $entry['readable'] === false) {
            $stats['errors']++;
            $stats['error_details'][] = [
                'path' => $diskPath,
                'reason' => 'Fichier illisible (droits insuffisants) — exécutez : php artisan ged:fix-perms',
            ];
            $stats['files_skipped']++;

            return;
        }

        // ── Validation MIME ────────────────────────────────────────────────
        $ext = strtolower(pathinfo($entry['name'], PATHINFO_EXTENSION));
        $mimeType = $this->mimeFromExtension($ext);

        if ($mimeType === null || (! empty($this->allowedMimes) && ! in_array($mimeType, $this->allowedMimes, true))) {
            $stats['errors']++;
            $stats['error_details'][] = [
                'path' => $diskPath,
                'reason' => 'Type de fichier non autorisé (.'.$ext.')',
            ];
            $stats['files_skipped']++;

            return;
        }

        // ── Validation taille ──────────────────────────────────────────────
        if ($entry['size'] > $this->maxFileSize) {
            $stats['errors']++;
            $stats['error_details'][] = [
                'path' => $diskPath,
                'reason' => sprintf(
                    'Fichier trop volumineux (%s Mo, max %s Mo)',
                    round($entry['size'] / 1024 / 1024, 1),
                    round($this->maxFileSize / 1024 / 1024),
                ),
            ];
            $stats['files_skipped']++;

            return;
        }

        // ── Ingestion ──────────────────────────────────────────────────────
        try {
            GedDocument::create([
                'folder_id' => $folder->id,
                'name' => $entry['name'],
                'disk_path' => $diskPath,
                'mime_type' => $mimeType,
                'size_bytes' => $entry['size'],
                'current_version' => 1,
                'created_by' => $folder->created_by,
            ]);

            $stats['files_added']++;
            Log::info('GED sync — document ingéré', ['path' => $diskPath]);

        } catch (\Throwable $e) {
            Log::error('GED sync — erreur ingestion', ['path' => $diskPath, 'error' => $e->getMessage()]);
            $stats['errors']++;
            $stats['error_details'][] = [
                'path' => $diskPath,
                'reason' => 'Erreur base de données : '.$e->getMessage(),
            ];
        }
    }

    // =========================================================================
    // Purges
    // =========================================================================

    /**
     * Soft-delete les GedDocument dont le fichier n'existe plus sur le NAS.
     * Ne touche que les documents associés à un dossier NAS (nas_path non null).
     *
     * @param  array<string, mixed>  $stats
     */
    private function purgeOrphanDocuments(GedStorageInterface $storage, array &$stats): void
    {
        GedDocument::whereHas('folder', fn ($q) => $q->whereNotNull('nas_path'))
            ->each(function (GedDocument $doc) use ($storage, &$stats): void {
                try {
                    if (! $storage->exists($doc->disk_path)) {
                        Log::info('GED sync — document orphelin supprimé', ['id' => $doc->id, 'path' => $doc->disk_path]);
                        $doc->delete();
                        $stats['files_removed']++;
                    }
                } catch (\Throwable $e) {
                    Log::warning('GED sync — impossible de vérifier fichier', [
                        'id' => $doc->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            });
    }

    /**
     * Soft-delete les GedFolder NAS (nas_path non null) vides dont le répertoire a disparu.
     * Traite les enfants avant les parents (ordre feuilles → racine).
     *
     * @param  array<string, mixed>  $stats
     */
    private function purgeOrphanFolders(GedStorageInterface $storage, array &$stats): void
    {
        // Trier : profondeur décroissante (compter les '/' dans path)
        $folders = GedFolder::whereNotNull('nas_path')
            ->withCount(['documents', 'children'])
            ->get()
            ->sortByDesc(fn (GedFolder $f) => substr_count($f->path, '/'));

        foreach ($folders as $folder) {
            try {
                if ($storage->exists($folder->nas_path)) {
                    continue;
                }

                // Compter ce qui reste (les purges précédentes ont peut-être tout vidé)
                $remaining = $folder->documents()->count() + GedFolder::where('parent_id', $folder->id)->count();

                if ($remaining > 0) {
                    Log::info('GED sync — dossier NAS absent mais non vide, conservé', [
                        'id' => $folder->id,
                        'nas_path' => $folder->nas_path,
                        'remaining' => $remaining,
                    ]);

                    continue;
                }

                Log::info('GED sync — dossier orphelin supprimé', ['id' => $folder->id, 'nas_path' => $folder->nas_path]);
                $folder->delete();
                $stats['folders_removed']++;

            } catch (\Throwable $e) {
                Log::warning('GED sync — erreur vérif dossier', ['id' => $folder->id, 'error' => $e->getMessage()]);
            }
        }
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Calcule le path logique GED d'un dossier d'après son slug et son parent.
     */
    private function computePath(string $slug, ?int $parentId): string
    {
        if ($parentId === null) {
            return '/'.$slug;
        }

        $parent = GedFolder::findOrFail($parentId);

        return rtrim($parent->path, '/').'/'.$slug;
    }

    /**
     * Détecte le MIME type à partir de l'extension.
     * Retourne null si l'extension est inconnue.
     */
    private function mimeFromExtension(string $ext): ?string
    {
        $map = [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'odt' => 'application/vnd.oasis.opendocument.text',
            'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
            'odp' => 'application/vnd.oasis.opendocument.presentation',
            'odg' => 'application/vnd.oasis.opendocument.graphics',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'tif' => 'image/tiff',
            'tiff' => 'image/tiff',
            'svg' => 'image/svg+xml',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
            'html' => 'text/html',
            'zip' => 'application/zip',
        ];

        return $map[$ext] ?? null;
    }

    /** @return array<string, mixed> */
    private function emptyStats(array $extra = []): array
    {
        return array_merge([
            'folders_created' => 0,
            'folders_found' => 0,
            'folders_removed' => 0,
            'files_added' => 0,
            'files_skipped' => 0,
            'files_removed' => 0,
            'errors' => 0,
            'error_details' => [],
        ], $extra);
    }
}
