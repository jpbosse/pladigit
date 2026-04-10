<?php

namespace App\Http\Controllers\Ged;

use App\Http\Controllers\Controller;
use App\Models\Tenant\GedDocument;
use App\Models\Tenant\GedDocumentVersion;
use App\Models\Tenant\GedFolder;
use App\Models\Tenant\User;
use App\Services\Ged\GedStorageInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Vérification d'intégrité entre le stockage GED et la base de données.
 *
 * Cinq catégories d'anomalies :
 *   1. Orphelins BDD actifs   : GedDocument actif dont le fichier n'existe plus sur le stockage.
 *   2. Versions orphelines    : GedDocumentVersion dont le fichier n'existe plus.
 *   3. Documents supprimés    : GedDocument soft-deleted (corbeille — à purger définitivement).
 *   4. Dossiers supprimés     : GedFolder soft-deleted (corbeille — à purger définitivement).
 *   5. Orphelins stockage     : fichiers physiques sans enregistrement BDD correspondant.
 *
 * Accessible uniquement aux rôles Admin / Président / DGS.
 */
class GedIntegrityController extends Controller
{
    private const ALLOWED_ROLES = ['admin', 'president', 'dgs'];

    public function __construct(private readonly GedStorageInterface $storage) {}

    // =========================================================================
    // Affichage
    // =========================================================================

    public function index(): View
    {
        $this->authorizeRole();

        return view('ged.integrity.index');
    }

    // =========================================================================
    // Scan AJAX
    // =========================================================================

    public function scan(): JsonResponse
    {
        $this->authorizeRole();

        set_time_limit(300);

        // ── 1. Orphelins BDD actifs ───────────────────────────────────────────
        // GedDocument actif dont le fichier physique n'existe plus
        $dbOrphans = [];

        GedDocument::with('folder:id,name,path', 'creator:id,name')
            ->chunkById(200, function ($docs) use (&$dbOrphans): void {
                foreach ($docs as $doc) {
                    try {
                        if (! $this->storage->exists($doc->disk_path)) {
                            $dbOrphans[] = [
                                'id' => $doc->id,
                                'name' => $doc->name,
                                'disk_path' => $doc->disk_path,
                                'size_bytes' => $doc->size_bytes,
                                'folder_name' => $doc->folder !== null ? $doc->folder->name : '—',
                                'folder_path' => $doc->folder !== null ? $doc->folder->path : '—',
                                'created_at' => $doc->created_at->format('d/m/Y'),
                            ];
                        }
                    } catch (\Throwable $e) {
                        Log::warning('GedIntegrityController::scan — vérification doc actif échouée', [
                            'doc_id' => $doc->id,
                            'disk_path' => $doc->disk_path,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        // ── 2. Versions archivées orphelines ──────────────────────────────────
        // GedDocumentVersion dont le fichier physique n'existe plus
        $versionOrphans = [];

        GedDocumentVersion::with('document:id,name', 'uploader:id,name')
            ->chunkById(200, function ($versions) use (&$versionOrphans): void {
                foreach ($versions as $v) {
                    try {
                        if (! $this->storage->exists($v->disk_path)) {
                            $versionOrphans[] = [
                                'id' => $v->id,
                                'version_number' => $v->version_number,
                                'disk_path' => $v->disk_path,
                                'size_bytes' => $v->size_bytes,
                                'doc_name' => $v->document !== null ? $v->document->name : '—',
                                'doc_id' => $v->document_id,
                                'created_at' => $v->created_at->format('d/m/Y'),
                            ];
                        }
                    } catch (\Throwable $e) {
                        Log::warning('GedIntegrityController::scan — vérification version échouée', [
                            'version_id' => $v->id,
                            'disk_path' => $v->disk_path,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        // ── 3. Documents soft-deleted ─────────────────────────────────────────
        $softDeletedDocs = GedDocument::onlyTrashed()
            ->with('folder:id,name,path')
            ->get()
            ->map(fn (GedDocument $doc) => [
                'id' => $doc->id,
                'name' => $doc->name,
                'disk_path' => $doc->disk_path,
                'size_bytes' => $doc->size_bytes,
                'folder_name' => $doc->folder !== null ? $doc->folder->name : '—',
                'deleted_at' => $doc->deleted_at?->format('d/m/Y H:i') ?? '—',
            ])
            ->values()
            ->toArray();

        // ── 4. Dossiers soft-deleted ──────────────────────────────────────────
        $softDeletedFolders = GedFolder::onlyTrashed()
            ->withCount(['documents' => fn ($q) => $q->withTrashed()])
            ->get()
            ->map(fn (GedFolder $folder) => [
                'id' => $folder->id,
                'name' => $folder->name,
                'path' => $folder->path,
                'docs_count' => $folder->documents_count,
                'deleted_at' => $folder->deleted_at?->format('d/m/Y H:i') ?? '—',
            ])
            ->values()
            ->toArray();

        // ── 5. Orphelins stockage ─────────────────────────────────────────────
        // Fichiers physiques sans enregistrement BDD
        $knownPaths = GedDocument::pluck('disk_path')->flip()->toArray();
        $knownPaths += GedDocumentVersion::pluck('disk_path')->flip()->toArray();

        $storageOrphans = [];

        try {
            $allFiles = $this->listStorageFilesRecursive('');
            foreach ($allFiles as $file) {
                if (! array_key_exists($file['path'], $knownPaths)) {
                    $storageOrphans[] = $file;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('GedIntegrityController::scan — listage stockage échoué', [
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'db_orphans' => $dbOrphans,
            'version_orphans' => $versionOrphans,
            'soft_deleted_docs' => $softDeletedDocs,
            'soft_deleted_folders' => $softDeletedFolders,
            'storage_orphans' => $storageOrphans,
            'scanned_at' => now()->format('d/m/Y H:i:s'),
        ]);
    }

    // =========================================================================
    // Actions de correction
    // =========================================================================

    /**
     * Soft-delete les documents actifs dont le fichier est absent du stockage.
     */
    public function purgeDbOrphans(Request $request): JsonResponse
    {
        $this->authorizeRole();

        $validated = $request->validate([
            'doc_ids' => ['required', 'array', 'min:1'],
            'doc_ids.*' => ['integer'],
        ]);

        $deleted = 0;
        foreach ($validated['doc_ids'] as $id) {
            $doc = GedDocument::find((int) $id);
            if (! $doc) {
                continue;
            }
            $doc->delete();
            $deleted++;
        }

        return response()->json(['deleted' => $deleted]);
    }

    /**
     * Hard-delete les enregistrements GedDocumentVersion orphelins (fichier absent).
     */
    public function purgeVersionOrphans(Request $request): JsonResponse
    {
        $this->authorizeRole();

        $validated = $request->validate([
            'version_ids' => ['required', 'array', 'min:1'],
            'version_ids.*' => ['integer'],
        ]);

        $deleted = GedDocumentVersion::whereIn('id', array_map('intval', $validated['version_ids']))
            ->delete();

        return response()->json(['deleted' => $deleted]);
    }

    /**
     * Hard-delete définitif des documents soft-deleted (cascade sur les versions).
     */
    public function purgeSoftDocs(Request $request): JsonResponse
    {
        $this->authorizeRole();

        $validated = $request->validate([
            'doc_ids' => ['required', 'array', 'min:1'],
            'doc_ids.*' => ['integer'],
        ]);

        $deleted = GedDocument::onlyTrashed()
            ->whereIn('id', array_map('intval', $validated['doc_ids']))
            ->forceDelete();

        return response()->json(['deleted' => $deleted]);
    }

    /**
     * Hard-delete définitif des dossiers soft-deleted.
     */
    public function purgeSoftFolders(Request $request): JsonResponse
    {
        $this->authorizeRole();

        $validated = $request->validate([
            'folder_ids' => ['required', 'array', 'min:1'],
            'folder_ids.*' => ['integer'],
        ]);

        $deleted = 0;
        foreach ($validated['folder_ids'] as $id) {
            $folder = GedFolder::onlyTrashed()->find((int) $id);
            if (! $folder) {
                continue;
            }
            $folder->forceDelete();
            $deleted++;
        }

        return response()->json(['deleted' => $deleted]);
    }

    /**
     * Supprime physiquement les fichiers orphelins du stockage.
     */
    public function purgeStorageOrphans(Request $request): JsonResponse
    {
        $this->authorizeRole();

        $validated = $request->validate([
            'paths' => ['required', 'array', 'min:1'],
            'paths.*' => ['string'],
        ]);

        $deleted = 0;
        foreach ($validated['paths'] as $path) {
            try {
                if ($this->storage->delete((string) $path)) {
                    $deleted++;
                }
            } catch (\Throwable $e) {
                Log::warning('GedIntegrityController::purgeStorageOrphans — suppression échouée', [
                    'path' => $path,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json(['deleted' => $deleted]);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Liste récursivement tous les fichiers du stockage GED.
     *
     * @return array<int, array{name: string, path: string, size: int, mtime: int}>
     */
    private function listStorageFilesRecursive(string $directory): array
    {
        $results = [];

        try {
            foreach ($this->storage->listDirectory($directory) as $entry) {
                if ($entry['type'] === 'file') {
                    $results[] = [
                        'name' => $entry['name'],
                        'path' => $entry['path'],
                        'size' => $entry['size'],
                        'mtime' => $entry['mtime'],
                    ];
                } elseif ($entry['type'] === 'dir') {
                    $results = array_merge(
                        $results,
                        $this->listStorageFilesRecursive($entry['path'])
                    );
                }
            }
        } catch (\Throwable $e) {
            Log::warning('GedIntegrityController::listStorageFilesRecursive — erreur', [
                'directory' => $directory,
                'error' => $e->getMessage(),
            ]);
        }

        return $results;
    }

    private function authorizeRole(): void
    {
        /** @var User $user */
        $user = auth()->user();
        abort_unless(
            $user && in_array($user->role, self::ALLOWED_ROLES, true),
            403,
            'Accès réservé aux administrateurs.'
        );
    }
}
