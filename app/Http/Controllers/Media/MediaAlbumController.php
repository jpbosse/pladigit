<?php

namespace App\Http\Controllers\Media;

use App\Http\Controllers\Controller;
use App\Models\Tenant\MediaAlbum;
use App\Models\Tenant\User;
use App\Services\AuditService;
use App\Services\Nas\NasManager;
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

        return view('media.albums.index', compact('albums'));
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

        $album = MediaAlbum::create([
            ...$validated,
            'created_by' => $user->id,
        ]);

        $this->audit->log('media.album.created', $user, [
            'model_type' => MediaAlbum::class,
            'model_id' => $album->id,
            'new' => ['name' => $album->name, 'visibility' => $album->visibility, 'parent_id' => $album->parent_id],
        ]);

        return redirect()
            ->route('media.albums.show', $album)
            ->with('success', "Album « {$album->name} » créé.");
    }

    /**
     * Affichage d'un album et de ses médias.
     */
    public function show(MediaAlbum $album)
    {
        /** @var User $user */
        $user = auth()->user();

        $this->authorize('view', $album);

        $album->load(['parent', 'children' => function ($q) {
            $q->withCount('items')->orderBy('name');
        }]);

        $items = $album->items()
            ->orderByDesc('created_at')
            ->paginate(48);

        $settings = \App\Models\Tenant\TenantSettings::firstOrCreate([]);

        $defaultCols = $settings->media_default_cols ?? 3;
        $userCols = auth()->user()->media_cols ?: $defaultCols;

        return view('media.albums.show', compact('album', 'items', 'defaultCols', 'userCols'));
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
     * Suppression (soft delete) d'un album.
     */
    public function destroy(MediaAlbum $album)
    {
        /** @var User $user */
        $user = auth()->user();

        $this->audit->log('media.album.deleted', $user, [
            'model_type' => MediaAlbum::class,
            'model_id' => $album->id,
            'old' => ['name' => $album->name],
        ]);

        $album->delete();

        return redirect()
            ->route('media.albums.index')
            ->with('success', "Album « {$album->name} » supprimé.");
    }

    /**
     * Test de connexion au NAS (appel AJAX depuis l'interface admin).
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
