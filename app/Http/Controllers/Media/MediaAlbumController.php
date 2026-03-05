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
     */
    public function create()
    {
        return view('media.albums.create');
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
            'new' => ['name' => $album->name, 'visibility' => $album->visibility],
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

        $this->authorizeView($album, $user);

        $items = $album->items()
            ->orderByDesc('created_at')
            ->paginate(48);

        $settings = \App\Models\Tenant\TenantSettings::firstOrCreate([]);
        $defaultCols = $settings->media_default_cols ?? 3;

        return view('media.albums.show', compact('album', 'items', 'defaultCols'));
    }

    /**
     * Formulaire d'édition d'un album.
     */
    public function edit(MediaAlbum $album)
    {
        return view('media.albums.edit', compact('album'));
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
        ]);

        /** @var User $user */
        $user = auth()->user();
        $old = $album->only(['name', 'description', 'visibility']);

        $album->update($validated);

        $this->audit->log('media.album.updated', $user, [
            'model_type' => MediaAlbum::class,
            'model_id' => $album->id,
            'old' => $old,
            'new' => $album->only(['name', 'description', 'visibility']),
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
    private function authorizeView(MediaAlbum $album, User $user): void
    {
        $visible = match ($album->visibility) {
            'public' => true,
            'restricted' => true,
            'private' => $album->created_by === $user->id,
            default => false,
        };

        if (! $visible) {
            abort(403, 'Vous n\'avez pas accès à cet album.');
        }
    }
}
