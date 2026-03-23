<?php

namespace App\Http\Controllers\Media;

use App\Http\Controllers\Controller;
use App\Models\Tenant\MediaAlbum;
use App\Models\Tenant\TenantSettings;
use App\Models\Tenant\User;
use App\Services\AuditService;
use App\Services\MediaService;
use App\Services\Nas\NasManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gestion des albums de la photothèque.
 *
 * Accès :
 *   - Tout utilisateur authentifié peut voir les albums visibles.
 *   - Admin/Président/DGS peuvent créer, modifier et supprimer.
 *   - Resp. Direction/Service peuvent créer (albums de leur équipe).
 */
class MediaAlbumController extends Controller
{
    public function __construct(
        private readonly NasManager $nasManager,
        private readonly AuditService $audit,
    ) {}

    /**
     * Liste des albums accessibles pour l'utilisateur courant.
     * Seuls les albums racine (parent_id = null) sont listés —
     * leurs sous-albums apparaissent imbriqués sous chaque carte.
     */
    public function index()
    {
        /** @var User $user */
        $user = auth()->user();
        $albums = MediaAlbum::visibleFor($user)
            ->whereNull('parent_id')
            ->withCount(['items', 'children'])
            ->orderByDesc('created_at')
            ->paginate(24);

        $albumTree = $this->buildSidebarTree($user);

        return view('media.albums.index', compact('albums', 'albumTree'));
    }

    /**
     * Formulaire de création d'un album.
     * $parentId optionnel — pré-sélectionne l'album parent si fourni.
     */
    public function create(Request $request)
    {
        /** @var User $user */
        $user = auth()->user();

        $selectedParent = $request->integer('parent_id') ?: null;
        $parentAlbums = $this->buildAlbumTree($user);

        return view('media.albums.create', compact('parentAlbums', 'selectedParent'));
    }

    /**
     * Enregistrement d'un nouvel album.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'visibility' => ['required', 'in:public,restricted,private'],
            'parent_id' => ['nullable', 'integer', 'exists:tenant.media_albums,id'],
        ]);

        /** @var User $user */
        $user = auth()->user();

        // Le nas_path est généré depuis le nom, imbriqué dans le parent si applicable
        $slug = \Illuminate\Support\Str::slug($validated['name']);
        $parent = isset($validated['parent_id']) ? MediaAlbum::find($validated['parent_id']) : null;
        $nasPath = $parent?->nas_path
            ? rtrim($parent->nas_path, '/').'/'.$slug
            : $slug;

        // Création automatique du dossier sur le NAS
        try {
            $nas = app(NasManager::class)->photoDriver();
            if (! $nas->exists($nasPath)) {
                $nas->mkdir($nasPath);
            }
        } catch (\Throwable $e) {
            return back()
                ->withErrors(['name' => "Impossible de créer le dossier NAS « {$nasPath} » : ".$e->getMessage()])
                ->withInput();
        }

        $album = MediaAlbum::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'visibility' => $validated['visibility'],
            'parent_id' => $validated['parent_id'] ?? null,
            'nas_path' => $nasPath,
            'created_by' => $user->id,
        ]);

        $this->audit->log('media.album.created', $user, [
            'model_type' => MediaAlbum::class,
            'model_id' => $album->id,
            'new' => ['name' => $album->name, 'visibility' => $album->visibility, 'nas_path' => $album->nas_path],
        ]);

        return redirect()
            ->route('media.albums.show', $album)
            ->with('success', "Album « {$album->name} » créé.");
    }

    /**
     * Affichage d'un album et de ses médias.
     */
    public function show(MediaAlbum $album, Request $request)
    {
        /** @var User $user */
        $user = auth()->user();

        $this->authorize('view', $album);

        $album->load(['parent', 'children' => function ($q) {
            $q->withCount('items')->orderBy('name');
        }]);

        // ── Pagination ───────────────────────────────────────
        $allowed = [10, 24, 48];
        $perPage = (int) $request->input('per_page', 24);
        $perPage = in_array($perPage, $allowed) ? $perPage : 24;
        $showAll = $request->input('per_page') === 'all';

        // ── Tri ──────────────────────────────────────────────
        $sortBy = $request->input('sort', 'date');
        $sortDir = $request->input('dir', 'desc');
        $sortDir = in_array($sortDir, ['asc', 'desc']) ? $sortDir : 'desc';

        $query = $album->items()->notThumbs();

        // ── Filtre par type ──────────────────────────────────
        $filterType = $request->input('type', 'all');
        $query = match ($filterType) {
            'images' => $query->where('mime_type', 'like', 'image/%'),
            'videos' => $query->where('mime_type', 'like', 'video/%'),
            'pdf' => $query->where('mime_type', 'application/pdf'),
            default => $query,
        };

        $query = match ($sortBy) {
            'name' => $query->orderBy('file_name', $sortDir),
            'size' => $query->orderBy('file_size_bytes', $sortDir),
            default => $query->orderBy('created_at', $sortDir),
        };

        $items = $showAll
            ? $query->paginate($query->count() ?: 1)->withQueryString()
            : $query->paginate($perPage)->withQueryString();

        // ── Arbre albums pour la sidebar ─────────────────────
        $albumTree = $this->buildSidebarTree($user);

        // ── Comptage total pour le stockage ──────────────────
        $totalItems = \App\Models\Tenant\MediaItem::count();

        $settings = \App\Models\Tenant\TenantSettings::firstOrCreate([]);
        $defaultCols = $settings->media_default_cols ?? 4;
        $userCols = $user->media_cols ?: $defaultCols;

        $itemsForJs = $items->map(function ($item) use ($album) {
            $exif = $item->exif_data ?? [];

            // ── Exposition ───────────────────────────────────────
            $exp = $exif['ExposureTime'] ?? null;
            $exposure = $exp
                ? (is_float($exp) && $exp < 1 ? '1/'.round(1 / $exp).' s' : $exp.' s')
                : null;

            // ── Focale ───────────────────────────────────────────
            $fl = $exif['FocalLength'] ?? null;
            $focal = null;
            if ($fl) {
                $flStr = (floor($fl) == $fl ? (int) $fl : round($fl, 1)).'mm';
                if (! empty($exif['FocalLengthIn35mmFilm'])) {
                    $flStr .= ' ('.$exif['FocalLengthIn35mmFilm'].'mm eq.)';
                }
                $focal = $flStr;
            }

            // ── Flash ────────────────────────────────────────────
            $flash = isset($exif['Flash'])
                ? (($exif['Flash'] & 1) ? 'Déclenché' : 'Non déclenché')
                : null;

            // ── Mesure ───────────────────────────────────────────
            $meteringLabels = [0 => 'Inconnu', 1 => 'Moyenne', 2 => 'Pondérée centrale', 3 => 'Spot', 4 => 'Multi-spot', 5 => 'Multi-zones', 6 => 'Partielle'];
            $metering = isset($exif['MeteringMode'])
                ? ($meteringLabels[$exif['MeteringMode']] ?? $exif['MeteringMode'])
                : null;

            // ── Balance des blancs ────────────────────────────────
            $whiteBalance = isset($exif['WhiteBalance'])
                ? ($exif['WhiteBalance'] == 0 ? 'Auto' : 'Manuel')
                : null;

            // ── Mode exposition ───────────────────────────────────
            $exposureModeLabels = [0 => 'Auto', 1 => 'Manuel', 2 => 'Auto bracketing'];
            $exposureMode = isset($exif['ExposureMode'])
                ? ($exposureModeLabels[$exif['ExposureMode']] ?? $exif['ExposureMode'])
                : null;

            // ── GPS ───────────────────────────────────────────────
            $gps = $item->gpsCoordinates();
            $gpsLabel = $gps
                ? number_format(abs($gps['lat']), 5).'°'.($gps['lat'] >= 0 ? 'N' : 'S').' '.number_format(abs($gps['lng']), 5).'°'.($gps['lng'] >= 0 ? 'E' : 'O')
                : null;
            $gpsUrl = $gps
                ? 'https://www.openstreetmap.org/?mlat='.$gps['lat'].'&mlon='.$gps['lng'].'&zoom=15'
                : null;
            $altitude = isset($exif['GPSAltitude']) ? round($exif['GPSAltitude']).' m' : null;

            return [
                'id' => $item->id,
                'name' => $item->caption ?? $item->file_name,
                'size' => $item->humanSize(),
                'dims' => $item->width_px ? $item->width_px.' × '.$item->height_px.' px' : null,
                'date' => $item->created_at->format('d/m/Y H:i'),
                'taken_at' => $item->takenAt()?->format('d/m/Y H:i') ?? null,
                'camera' => ! empty($exif['Make'])
                    ? trim(($exif['Make'] ?? '').' '.($exif['Model'] ?? ''))
                    : null,
                'software' => ! empty($exif['Software']) ? $exif['Software'] : null,
                'exposure' => $exposure,
                'aperture' => ! empty($exif['FNumber'])
                    ? 'f/'.number_format($exif['FNumber'], 1)
                    : null,
                'iso' => $exif['ISOSpeedRatings'] ?? null,
                'focal' => $focal,
                'flash' => $flash,
                'metering' => $metering,
                'white_balance' => $whiteBalance,
                'exposure_mode' => $exposureMode,
                'gps_label' => $gpsLabel,
                'gps_url' => $gpsUrl,
                'altitude' => $altitude,
                'sha256' => $item->sha256_hash ? substr($item->sha256_hash, 0, 12).'…' : null,
                'isImage' => $item->isImage(),
                'isPdf' => $item->isPdf(),
                'isVideo' => $item->isVideo(),
                'thumb' => $item->isImage() ? route('media.items.serve', [$album, $item, 'thumb']) : null,
                'full' => route('media.items.serve', [$album, $item, 'full']),
                'download' => route('media.items.download', [$album, $item]),
                'destroy' => route('media.items.destroy', [$album, $item]),
                'mime' => $item->mime_type,
            ];
        })->values();

        return view('media.albums.show', compact(
            'album', 'items', 'itemsForJs', 'defaultCols', 'userCols',
            'perPage', 'showAll', 'sortBy', 'sortDir', 'filterType',
            'albumTree', 'totalItems'
        ));
    }

    /**
     * Formulaire d'édition d'un album.
     */
    public function edit(MediaAlbum $album)
    {
        /** @var User $user */
        $user = auth()->user();

        // Tous les albums sauf l'album lui-même et tous ses descendants
        $excludeIds = array_merge([$album->id], $album->descendantIds());
        $parentAlbums = $this->buildAlbumTree($user, $excludeIds);

        return view('media.albums.edit', compact('album', 'parentAlbums'));
    }

    /**
     * Mise à jour d'un album.
     */
    public function update(Request $request, MediaAlbum $album)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'visibility' => ['required', 'in:public,restricted,private'],
            'parent_id' => ['nullable', 'integer', 'exists:tenant.media_albums,id', "not_in:{$album->id}"],
        ]);

        /** @var User $user */
        $user = auth()->user();
        $old = $album->only(['name', 'description', 'visibility', 'parent_id']);

        $album->update($validated);

        $this->audit->log('media.album.updated', $user, [
            'model_type' => MediaAlbum::class,
            'model_id' => $album->id,
            'old' => $old,
            'new' => $album->only(['name', 'description', 'visibility', 'parent_id']),
        ]);

        return redirect()
            ->route('media.albums.show', $album)
            ->with('success', "Album « {$album->name} » mis à jour.");
    }

    /**
     * Déclenche une synchronisation NAS → BDD.
     * Accessible à tous les utilisateurs authentifiés de la photothèque.
     */
    public function syncNas(Request $request, MediaService $mediaService): JsonResponse
    {
        try {
            /** @var User $user */
            $user = auth()->user();
            $owner = User::where('role', 'admin')->first() ?? $user;

            $result = $mediaService->syncAlbumTree(
                nasRoot: '',
                owner: $owner,
                deep: false,
            );

            TenantSettings::firstOrCreate([])->update(['nas_photo_last_sync_at' => now()]);

            $parts = [];
            if ($result['files_added'] > 0) {
                $parts[] = $result['files_added'].' fichier(s) ajouté(s)';
            }
            if ($result['files_removed'] > 0) {
                $parts[] = $result['files_removed'].' fichier(s) supprimé(s)';
            }
            if ($result['albums_created'] > 0) {
                $parts[] = $result['albums_created'].' album(s) créé(s)';
            }
            if ($result['albums_removed'] > 0) {
                $parts[] = $result['albums_removed'].' album(s) supprimé(s)';
            }
            if ($result['errors'] > 0) {
                $parts[] = $result['errors'].' erreur(s)';
            }

            $message = empty($parts) ? 'Aucune modification détectée.' : implode(', ', $parts).'.';

            return response()->json([
                'ok' => $result['errors'] === 0,
                'message' => $message,
                'stats' => $result,
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Erreur : '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Suppression (soft delete) d'un album.
     */
    public function destroy(MediaAlbum $album, MediaService $mediaService)
    {
        /** @var User $user */
        $user = auth()->user();

        $this->authorize('manage', $album);

        $this->audit->log('media.album.deleted', $user, [
            'model_type' => MediaAlbum::class,
            'model_id' => $album->id,
            'old' => ['name' => $album->name],
        ]);

        // Suppression physique de tous les fichiers NAS de l'album (et sous-albums)
        $mediaService->deleteAlbumFiles($album);

        $album->delete();

        return redirect()
            ->route('media.albums.index')
            ->with('success', "Album « {$album->name} » supprimé.");
    }

    /**
     * Recherche d'albums pour la sidebar (AJAX).
     * Retourne JSON [{id, name, path, items_count, url}]
     * Limité à 20 résultats — utilisé par albumSearch() dans index.blade.php.
     */
    public function search(Request $request): \Illuminate\Http\JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $q = trim($request->input('q', ''));

        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $albums = MediaAlbum::visibleFor($user)
            ->where('name', 'like', '%'.$q.'%')
            ->withCount('items')
            ->with('parent:id,name')
            ->orderBy('name')
            ->limit(20)
            ->get();

        return response()->json(
            $albums->map(fn ($album) => [
                'id' => $album->id,
                'name' => $album->name,
                'path' => $album->parent ? $album->parent->name.' / '.$album->name : null,
                'items_count' => $album->items_count,
                'url' => route('media.albums.show', $album),
            ])
        );
    }

    /**
     * Retourne JSON { ok: bool, message: string }
     */
    public function testNasConnection()
    {
        try {
            $nas = $this->nasManager->driver();
            $ok = $nas->testConnection();

            return response()->json([
                'ok' => $ok,
                'message' => $ok ? 'Connexion NAS établie.' : 'Connexion NAS échouée.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Erreur : '.$e->getMessage(),
            ], 500);
        }
    }

    // ── Helpers privés ───────────────────────────────────────

    /**
     * Construit l'arbre des albums pour la sidebar.
     * Retourne une collection d'albums racine avec leurs enfants chargés.
     */
    private function buildSidebarTree(User $user): \Illuminate\Support\Collection
    {
        // Limité à 30 albums racine — la recherche AJAX prend le relais au-delà
        return MediaAlbum::visibleFor($user)
            ->whereNull('parent_id')
            ->withCount('items')
            ->orderBy('name')
            ->limit(30)
            ->get();
    }

    /**
     * Construit une liste à plat de tous les albums accessibles,
     * indentés selon leur profondeur, pour les selects <option>.
     *
     * Retourne une Collection de stdClass { id, name (indenté), depth }
     *
     * @param  array<int>  $excludeIds  Albums à exclure (ex: album courant + descendants)
     */
    private function buildAlbumTree(User $user, array $excludeIds = []): \Illuminate\Support\Collection
    {
        $all = MediaAlbum::visibleFor($user)
            ->whereNotIn('id', $excludeIds)
            ->orderBy('name')
            ->get(['id', 'parent_id', 'name']);

        // Indexer par id pour accès rapide
        $byId = $all->keyBy('id');

        // Construire l'arbre en récursif
        $result = collect();
        $this->appendChildren($byId, null, 0, $result, $excludeIds);

        return $result;
    }

    /**
     * Ajoute récursivement les enfants d'un parent dans $result.
     *
     * @param  array<int>  $excludeIds
     */
    private function appendChildren(
        \Illuminate\Support\Collection $byId,
        ?int $parentId,
        int $depth,
        \Illuminate\Support\Collection &$result,
        array $excludeIds
    ): void {
        $children = $byId->filter(fn ($a) => $a->parent_id === $parentId);

        foreach ($children as $album) {
            if (in_array($album->id, $excludeIds)) {
                continue;
            }

            $prefix = $depth > 0 ? str_repeat('　', $depth).'└── ' : '';

            $item = new \stdClass;
            $item->id = $album->id;
            $item->name = $prefix.$album->name;
            $item->depth = $depth;

            $result->push($item);

            $this->appendChildren($byId, $album->id, $depth + 1, $result, $excludeIds);
        }
    }
}
