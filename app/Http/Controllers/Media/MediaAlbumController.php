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
     */
    public function index()
    {
        /** @var User $user */
        $user = auth()->user();
        $albums = MediaAlbum::visibleFor($user)
            ->withCount('items')
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

        $parentAlbums = MediaAlbum::visibleFor($user)
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get(['id', 'name']);

        $selectedParent = $request->integer('parent_id') ?: null;

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

        // Albums racine visibles — exclure l'album lui-même et ses enfants
        // pour éviter les boucles circulaires
        $childIds = $album->children()->pluck('id')->toArray();
        $excludeIds = array_merge([$album->id], $childIds);

        $parentAlbums = MediaAlbum::visibleFor($user)
            ->whereNull('parent_id')
            ->whereNotIn('id', $excludeIds)
            ->orderBy('name')
            ->get(['id', 'name']);

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
     * Vérifie que l'utilisateur peut voir cet album.
     */
}
