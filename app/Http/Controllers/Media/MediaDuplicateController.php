<?php

namespace App\Http\Controllers\Media;

use App\Http\Controllers\Controller;
use App\Models\Tenant\MediaAlbum;
use App\Models\Tenant\MediaItem;
use App\Models\Tenant\User;
use App\Services\Nas\NasManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

/**
 * Gestion des doublons de la photothèque.
 *
 * Un "groupe doublon" = plusieurs MediaItem ayant le même sha256_hash.
 * Accessible uniquement aux rôles Admin / Président / DGS.
 */
class MediaDuplicateController extends Controller
{
    private const ALLOWED_ROLES = ['admin', 'president', 'dgs'];

    private const GROUPS_PER_PAGE = 15;

    public function __construct(private readonly NasManager $nasManager) {}

    // =========================================================================
    // Affichage
    // =========================================================================

    public function index(Request $request)
    {
        $this->authorizeRole();

        /** @var User $user */
        $user = auth()->user();

        $page = max(1, $request->integer('page', 1));
        $perPage = self::GROUPS_PER_PAGE;

        // ── Hashes ayant plusieurs exemplaires à des emplacements DISTINCTS ─────
        // COUNT(DISTINCT file_path) > 1 : exclut les doublons BDD (même chemin,
        // plusieurs enregistrements) — ceux-ci sont traités par la page Intégrité.
        $hashQuery = MediaItem::whereNotNull('sha256_hash')
            ->selectRaw('sha256_hash, COUNT(*) as dup_count, SUM(file_size_bytes) as total_bytes')
            ->groupBy('sha256_hash')
            ->havingRaw('COUNT(DISTINCT file_path) > 1')
            ->orderByDesc('dup_count')
            ->orderBy('sha256_hash');

        // Charger TOUS les hashes pour construire les groupes réels,
        // puis paginer côté PHP après le filtre unique(file_path).
        // Cela évite la surestimation de $totalGroups qui causait une pagination cassée.
        $hashes = $hashQuery->get();

        // ── Items de ces hashes avec leurs albums ─────────────────────────────
        $allGroups = collect();

        if ($hashes->isNotEmpty()) {
            $itemsByHash = MediaItem::whereIn('sha256_hash', $hashes->pluck('sha256_hash'))
                ->with('album:id,name,nas_path')
                ->orderBy('created_at')
                ->orderBy('id')
                ->get()
                ->groupBy('sha256_hash')
                // Un seul représentant par file_path (le plus ancien) : les doublons
                // BDD (même chemin) sont traités par la page Intégrité, pas ici.
                ->map(fn ($items) => $items->unique('file_path')->values());

            foreach ($hashes as $row) {
                $items = $itemsByHash[$row->sha256_hash] ?? collect();
                if ($items->count() < 2) {
                    continue;
                }
                /** @var object{sha256_hash: string, dup_count: int|string, total_bytes: int|string} $row */
                $allGroups->push([
                    'hash' => $row->sha256_hash,
                    'count' => $items->count(),
                    'total_bytes' => (int) $row->getAttribute('total_bytes'),
                    'items' => $items,
                ]);
            }
        }

        // ── Pagination précise sur les groupes réels ──────────────────────────
        $totalGroups = $allGroups->count();
        $groups = $allGroups->slice(($page - 1) * $perPage, $perPage)->values();

        $pagination = new LengthAwarePaginator(
            $groups,
            $totalGroups,
            $perPage,
            $page,
            ['path' => route('media.duplicates.index')]
        );

        // ── Stats globales ────────────────────────────────────────────────────
        $totalDupItems = MediaItem::where('is_duplicate', true)->count();
        $wastedBytes = MediaItem::where('is_duplicate', true)->sum('file_size_bytes');

        // Sidebar
        $albumTree = MediaAlbum::visibleFor($user)
            ->whereNull('parent_id')
            ->withCount(['items', 'children'])
            ->with('coverItem')
            ->orderBy('name')
            ->get();

        return view('media.duplicates.index', compact(
            'groups',
            'pagination',
            'totalGroups',
            'totalDupItems',
            'wastedBytes',
            'albumTree',
        ));
    }

    // =========================================================================
    // Suppression en masse
    // =========================================================================

    public function destroySelected(Request $request): JsonResponse
    {
        $this->authorizeRole();

        $validated = $request->validate([
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['integer'],
        ]);

        $nas = $this->nasManager->photoDriver();
        $deleted = 0;
        $errors = [];
        $idsToDelete = array_map('intval', $validated['item_ids']);
        $deletedNasPaths = []; // évite de tenter de supprimer deux fois le même chemin dans un lot

        $items = MediaItem::whereIn('id', $idsToDelete)->get()->keyBy('id');

        foreach ($idsToDelete as $itemId) {
            $item = $items->get($itemId);
            if (! $item) {
                $errors[] = "Item #{$itemId} introuvable.";

                continue;
            }

            // Supprimer le fichier NAS affiché sous la photo.
            if ($item->file_path && ! isset($deletedNasPaths[$item->file_path])) {
                try {
                    if ($nas->exists($item->file_path)) {
                        $nas->deleteFile($item->file_path);
                    }

                    if ($item->thumb_path && $nas->exists($item->thumb_path)) {
                        $nas->deleteFile($item->thumb_path);
                    }

                    $deletedNasPaths[$item->file_path] = true;
                } catch (\Throwable $e) {
                    Log::warning('MediaDuplicateController::destroySelected — NAS exception', [
                        'item_id' => $item->id,
                        'file_path' => $item->file_path,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $item->forceDelete();
            $deleted++;
        }

        return response()->json(['deleted' => $deleted, 'errors' => $errors]);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

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
