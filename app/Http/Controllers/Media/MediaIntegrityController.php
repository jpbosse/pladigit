<?php

namespace App\Http\Controllers\Media;

use App\Http\Controllers\Controller;
use App\Models\Tenant\MediaAlbum;
use App\Models\Tenant\MediaItem;
use App\Models\Tenant\MediaShareLink;
use App\Models\Tenant\User;
use App\Services\Nas\NasManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Vérification d'intégrité entre le NAS et la base de données.
 *
 * Six catégories d'anomalies :
 *   - Orphelins BDD actifs   : items actifs dont le fichier n'existe plus sur le NAS.
 *   - Items supprimés        : items soft-deleted (à purger définitivement ou restaurer).
 *   - Albums orphelins NAS   : albums liés à un dossier NAS qui n'existe plus.
 *   - Albums supprimés       : albums soft-deleted (à purger définitivement).
 *   - Liens partagés orphelins : MediaShareLink dont l'album est soft-deleted.
 *   - Tags orphelins         : tags sans aucun item actif associé.
 *   - Orphelins NAS          : fichiers sur le NAS sans enregistrement BDD.
 *
 * Accessible uniquement aux rôles Admin / Président / DGS.
 */
class MediaIntegrityController extends Controller
{
    private const ALLOWED_ROLES = ['admin', 'president', 'dgs'];

    public function __construct(private readonly NasManager $nasManager) {}

    // =========================================================================
    // Affichage
    // =========================================================================

    public function index(Request $request)
    {
        $this->authorizeRole();

        /** @var User $user */
        $user = auth()->user();

        $albumTree = MediaAlbum::visibleFor($user)
            ->whereNull('parent_id')
            ->withCount(['items', 'children'])
            ->with('coverItem')
            ->orderBy('name')
            ->get();

        return view('media.integrity.index', compact('albumTree'));
    }

    // =========================================================================
    // Scan AJAX
    // =========================================================================

    public function scan(Request $request): JsonResponse
    {
        $this->authorizeRole();

        set_time_limit(300);

        $nas = $this->nasManager->photoDriver();

        // ── 1. Orphelins BDD actifs ──────────────────────────────────────────
        $dbOrphans = [];

        MediaItem::notThumbs()
            ->with('album:id,name')
            ->chunkById(200, function ($items) use ($nas, &$dbOrphans) {
                foreach ($items as $item) {
                    try {
                        if (! $nas->exists($item->file_path)) {
                            $dbOrphans[] = [
                                'id' => $item->id,
                                'file_name' => $item->file_name,
                                'file_path' => $item->file_path,
                                'file_size' => $item->file_size_bytes,
                                'album_id' => $item->album_id,
                                'album_name' => $item->album?->name ?? '—', // @phpstan-ignore-line nullsafe.neverNull
                                'created_at' => $item->created_at?->format('d/m/Y'),
                            ];
                        }
                    } catch (\Throwable $e) {
                        Log::warning('MediaIntegrityController::scan — impossible de vérifier item actif', [
                            'item_id' => $item->id,
                            'file_path' => $item->file_path,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        // ── 2. Items soft-deleted ─────────────────────────────────────────────
        $softDeletedItems = MediaItem::onlyTrashed()
            ->notThumbs()
            ->with('album:id,name')
            ->get()
            ->map(fn (MediaItem $item) => [
                'id' => $item->id,
                'file_name' => $item->file_name,
                'file_path' => $item->file_path,
                'file_size' => $item->file_size_bytes,
                'album_name' => $item->album?->name ?? '—', // @phpstan-ignore-line nullsafe.neverNull
                'deleted_at' => $item->deleted_at?->format('d/m/Y H:i') ?? '—',
            ])
            ->values()
            ->toArray();

        // ── 3. Albums orphelins NAS ───────────────────────────────────────────
        $orphanAlbums = [];

        MediaAlbum::whereNotNull('nas_path')
            ->withCount('items')
            ->get()
            ->each(function (MediaAlbum $album) use ($nas, &$orphanAlbums) {
                try {
                    if (! $nas->exists((string) $album->nas_path)) {
                        $orphanAlbums[] = [
                            'id' => $album->id,
                            'name' => $album->name,
                            'nas_path' => $album->nas_path,
                            'items_count' => $album->items_count,
                        ];
                    }
                } catch (\Throwable $e) {
                    Log::warning('MediaIntegrityController::scan — impossible de vérifier album', [
                        'album_id' => $album->id,
                        'nas_path' => $album->nas_path,
                        'error' => $e->getMessage(),
                    ]);
                }
            });

        // ── 4. Albums soft-deleted ────────────────────────────────────────────
        $softDeletedAlbums = MediaAlbum::onlyTrashed()
            ->withCount(['items' => fn ($q) => $q->withTrashed()])
            ->get()
            ->map(fn (MediaAlbum $album) => [
                'id' => $album->id,
                'name' => $album->name,
                'nas_path' => $album->nas_path ?? '—',
                'items_count' => $album->items_count,
                'deleted_at' => $album->deleted_at?->format('d/m/Y H:i') ?? '—',
            ])
            ->values()
            ->toArray();

        // ── 5. Liens de partage orphelins (album soft-deleted) ────────────────
        // whereDoesntHave utilise le scope SoftDeletes → détecte les albums soft-deleted
        $orphanShareLinks = MediaShareLink::whereDoesntHave('album')
            ->get()
            ->map(fn (MediaShareLink $link) => [
                'id' => $link->id,
                'album_id' => $link->album_id,
                'token' => substr($link->token, 0, 12).'…',
                'expires_at' => $link->expires_at?->format('d/m/Y') ?? 'jamais',
                'created_at' => $link->created_at?->format('d/m/Y'),
            ])
            ->values()
            ->toArray();

        // ── 6. Orphelins NAS ──────────────────────────────────────────────────
        // listFiles() est désormais non-récursif → on parcourt l'arborescence manuellement.
        $knownPaths = MediaItem::notThumbs()->pluck('file_path')->flip()->toArray();
        $nasOrphans = [];

        try {
            $nasFiles = $this->listNasFilesRecursive($nas, '');
            foreach ($nasFiles as $file) {
                if (! array_key_exists($file['path'], $knownPaths)) {
                    $nasOrphans[] = [
                        'path' => $file['path'],
                        'name' => $file['name'],
                        'size' => $file['size'],
                        'mtime' => $file['mtime'],
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('MediaIntegrityController::scan — listFiles échoué', [
                'error' => $e->getMessage(),
            ]);
        }

        // ── 7. Doublons BDD (même file_path, plusieurs enregistrements) ───────
        // Ces items sont du même fichier physique mais ont été importés plusieurs
        // fois → ils n'apparaissent plus dans la page Doublons (qui ne compare que
        // des chemins distincts). On les détecte ici pour purge DB.
        $dbDuplicatePaths = MediaItem::notThumbs()
            ->selectRaw('file_path, COUNT(*) as cnt')
            ->groupBy('file_path')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('cnt', 'file_path');

        $dbDuplicates = [];
        foreach ($dbDuplicatePaths as $path => $cnt) {
            $items = MediaItem::notThumbs()
                ->where('file_path', $path)
                ->with('album:id,name')
                ->orderBy('created_at')
                ->orderBy('id')
                ->get();

            // Le premier (plus ancien) est l'exemplaire à conserver
            $keepId = $items->first()?->id;
            foreach ($items->skip(1) as $item) {
                $dbDuplicates[] = [
                    'id' => $item->id,
                    'keep_id' => $keepId,
                    'file_name' => $item->file_name,
                    'file_path' => $item->file_path,
                    'album_name' => $item->album?->name ?? '—', // @phpstan-ignore-line nullsafe.neverNull
                    'created_at' => $item->created_at?->format('d/m/Y H:i') ?? '—',
                    'copies' => (int) $cnt,
                ];
            }
        }

        return response()->json([
            'db_orphans' => $dbOrphans,
            'soft_deleted_items' => $softDeletedItems,
            'orphan_albums' => $orphanAlbums,
            'soft_deleted_albums' => $softDeletedAlbums,
            'orphan_share_links' => $orphanShareLinks,
            'nas_orphans' => $nasOrphans,
            'db_duplicates' => $dbDuplicates,
            'scanned_at' => now()->format('d/m/Y H:i:s'),
        ]);
    }

    // =========================================================================
    // Purge des orphelins BDD actifs (soft-delete)
    // =========================================================================

    public function purgeDbOrphans(Request $request): JsonResponse
    {
        $this->authorizeRole();

        $validated = $request->validate([
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['integer'],
        ]);

        $deleted = 0;

        foreach ($validated['item_ids'] as $itemId) {
            $item = MediaItem::find((int) $itemId);
            if (! $item) {
                continue;
            }
            $item->delete();
            $deleted++;
        }

        return response()->json(['deleted' => $deleted]);
    }

    // =========================================================================
    // Purge définitive des items soft-deleted (hard-delete)
    // =========================================================================

    public function purgeDbSoftDeleted(Request $request): JsonResponse
    {
        $this->authorizeRole();

        $validated = $request->validate([
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['integer'],
        ]);

        $deleted = MediaItem::withTrashed()
            ->whereIn('id', array_map('intval', $validated['item_ids']))
            ->whereNotNull('deleted_at')
            ->forceDelete();

        return response()->json(['deleted' => $deleted]);
    }

    // =========================================================================
    // Purge définitive des albums soft-deleted (hard-delete)
    // =========================================================================

    public function purgeDbSoftAlbums(Request $request): JsonResponse
    {
        $this->authorizeRole();

        $validated = $request->validate([
            'album_ids' => ['required', 'array', 'min:1'],
            'album_ids.*' => ['integer'],
        ]);

        $deleted = 0;

        foreach ($validated['album_ids'] as $albumId) {
            $album = MediaAlbum::withTrashed()
                ->whereNotNull('deleted_at')
                ->find((int) $albumId);
            if (! $album) {
                continue;
            }
            $album->forceDelete();
            $deleted++;
        }

        return response()->json(['deleted' => $deleted]);
    }

    // =========================================================================
    // Suppression des albums orphelins NAS (vides uniquement)
    // =========================================================================

    public function purgeOrphanAlbums(Request $request): JsonResponse
    {
        $this->authorizeRole();

        $validated = $request->validate([
            'album_ids' => ['required', 'array', 'min:1'],
            'album_ids.*' => ['integer'],
        ]);

        $deleted = 0;

        foreach ($validated['album_ids'] as $albumId) {
            $album = MediaAlbum::withCount('items')->find((int) $albumId);
            if (! $album || $album->items_count > 0) {
                continue;
            }
            $album->delete();
            $deleted++;
        }

        return response()->json(['deleted' => $deleted]);
    }

    // =========================================================================
    // Suppression des liens de partage orphelins
    // =========================================================================

    public function purgeOrphanShareLinks(Request $request): JsonResponse
    {
        $this->authorizeRole();

        $validated = $request->validate([
            'link_ids' => ['required', 'array', 'min:1'],
            'link_ids.*' => ['integer'],
        ]);

        $deleted = MediaShareLink::whereDoesntHave('album')
            ->whereIn('id', array_map('intval', $validated['link_ids']))
            ->delete();

        return response()->json(['deleted' => $deleted]);
    }

    // =========================================================================
    // Purge des doublons BDD (même file_path, conserver le plus ancien)
    // =========================================================================

    public function purgeDbDuplicates(Request $request): JsonResponse
    {
        $this->authorizeRole();

        $validated = $request->validate([
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['integer'],
        ]);

        // Hard-delete des enregistrements en double (le fichier NAS n'est pas touché)
        $deleted = MediaItem::withTrashed()
            ->whereIn('id', array_map('intval', $validated['item_ids']))
            ->forceDelete();

        return response()->json(['deleted' => $deleted]);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Liste récursivement tous les fichiers NAS (listFiles() est non-récursif).
     *
     * @return array<int, array{name: string, path: string, size: int, mtime: int}>
     */
    private function listNasFilesRecursive(\App\Services\Nas\NasConnectorInterface $nas, string $directory): array
    {
        $results = [];

        try {
            foreach ($nas->listFiles($directory) as $file) {
                $results[] = $file;
            }
            foreach ($nas->listDirectories($directory) as $dir) {
                if (basename($dir['path']) === 'thumbs') {
                    continue;
                }
                $results = array_merge($results, $this->listNasFilesRecursive($nas, $dir['path']));
            }
        } catch (\Throwable $e) {
            Log::warning('MediaIntegrityController::listNasFilesRecursive — erreur', [
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
