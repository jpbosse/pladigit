<?php

namespace App\Http\Controllers\Media;

use App\Http\Controllers\Controller;
use App\Models\Tenant\MediaAlbum;
use App\Models\Tenant\MediaItem;
use App\Models\Tenant\TenantSettings;
use App\Models\Tenant\User;
use App\Services\AuditService;
use App\Services\MediaService;
use App\Services\Nas\NasManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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
            ->with('coverItem')
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
        $allowed = [5, 10, 20, 24, 48];
        $perPage = (int) $request->input('per_page', 10);
        $perPage = in_array($perPage, $allowed) ? $perPage : 10;
        $showAll = $request->input('per_page') === 'all';

        // ── Tri ──────────────────────────────────────────────
        $sortBy = $request->input('sort', 'date');
        $sortDir = $request->input('dir', 'desc');
        $sortDir = in_array($sortDir, ['asc', 'desc']) ? $sortDir : 'desc';

        $query = $album->items()->notThumbs();

        // ── Filtre par tag ───────────────────────────────────
        $filterTagId = $request->integer('tag_id') ?: null;
        if ($filterTagId) {
            $query->whereHas('tags', fn ($q) => $q->where('media_tags.id', $filterTagId));
        }

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
            'exif_date' => $sortDir === 'asc'
                // NULLS LAST en ASC : items sans EXIF après ceux qui en ont
                ? $query->orderByRaw('exif_taken_at IS NULL ASC, exif_taken_at ASC, created_at ASC')
                // NULLS LAST en DESC : items sans EXIF après ceux qui en ont
                : $query->orderByRaw('exif_taken_at IS NULL ASC, exif_taken_at DESC, created_at DESC'),
            default => $query->orderBy('created_at', $sortDir),
        };

        $items = $showAll
            ? $query->with('uploader', 'tags')->paginate($query->count() ?: 1)->withQueryString()
            : $query->with('uploader', 'tags')->paginate($perPage)->withQueryString();

        // ── Arbre albums pour la sidebar ─────────────────────
        $albumTree = $this->buildSidebarTree($user);

        // ── Ancêtres pour auto-dépliage de l'arbre ───────────
        $ancestorIds = [];
        $cursor = $album->parent_id ? $album->parent : null;
        while ($cursor) {
            $ancestorIds[] = $cursor->id;
            $cursor = $cursor->parent_id ? $cursor->parent : null;
        }
        $ancestorIds = array_reverse($ancestorIds);

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

            // ── Objectif ─────────────────────────────────────────
            $lens = null;
            if (! empty($exif['LensModel'])) {
                $lens = trim(($exif['LensMake'] ?? '').' '.$exif['LensModel']);
            } elseif (! empty($exif['LensMake'])) {
                $lens = $exif['LensMake'];
            }

            // ── Compensation d'exposition ─────────────────────────
            $exposureBias = null;
            if (isset($exif['ExposureBiasValue']) && $exif['ExposureBiasValue'] != 0) {
                $ev = (float) $exif['ExposureBiasValue'];
                $exposureBias = ($ev > 0 ? '+' : '').number_format($ev, 1).' EV';
            }

            // ── Type de scène ─────────────────────────────────────
            $sceneLabels = [0 => 'Standard', 1 => 'Portrait', 2 => 'Paysage', 3 => 'Scène de nuit', 4 => 'Paysage de nuit', 5 => 'Rétroéclairé', 6 => 'Crépuscule / Lever', 7 => 'Intérieur', 8 => 'Feu d\'artifice'];
            $sceneType = isset($exif['SceneCaptureType'])
                ? ($sceneLabels[$exif['SceneCaptureType']] ?? null)
                : null;

            // ── Auteur / Copyright ────────────────────────────────
            $artist = ! empty($exif['Artist']) ? $exif['Artist'] : null;
            $copyright = ! empty($exif['Copyright']) ? $exif['Copyright'] : null;

            // ── Espace colorimétrique ─────────────────────────────
            $colorSpaceLabels = [1 => 'sRGB', 2 => 'Adobe RGB', 65535 => 'Non calibré'];
            $colorSpace = isset($exif['ColorSpace'])
                ? ($colorSpaceLabels[$exif['ColorSpace']] ?? null)
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
                'lens' => $lens,
                'exposure' => $exposure,
                'exposure_bias' => $exposureBias,
                'aperture' => ! empty($exif['FNumber'])
                    ? 'f/'.number_format($exif['FNumber'], 1)
                    : null,
                'iso' => $exif['ISOSpeedRatings'] ?? null,
                'focal' => $focal,
                'flash' => $flash,
                'metering' => $metering,
                'white_balance' => $whiteBalance,
                'exposure_mode' => $exposureMode,
                'scene_type' => $sceneType,
                'artist' => $artist,
                'copyright' => $copyright,
                'color_space' => $colorSpace,
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
                'cover_url' => route('media.albums.cover', [$album, $item]),
                'is_cover' => $item->isImage(),
                'is_duplicate' => (bool) $item->is_duplicate,
                'caption' => $item->caption,
                'file_name' => $item->file_name,
                'uploader_name' => $item->uploader?->name,
                'caption_url' => route('media.items.updateCaption', [$album, $item]),
                'tags' => $item->relationLoaded('tags')
                    ? $item->tags->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])->values()
                    : [],
            ];
        })->values();

        $canAdmin = $album->canAdmin($user);
        $coverItem = $album->resolveCoverItem();

        return view('media.albums.show', compact(
            'album', 'items', 'itemsForJs', 'defaultCols', 'userCols',
            'perPage', 'showAll', 'sortBy', 'sortDir', 'filterType',
            'albumTree', 'ancestorIds', 'totalItems', 'canAdmin', 'coverItem'
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

        // Items images pour le sélecteur de couverture (max 24 pour l'UI)
        $coverItems = $album->items()
            ->where('mime_type', 'like', 'image/%')
            ->notThumbs()
            ->oldest()
            ->limit(24)
            ->get();

        $currentCover = $album->resolveCoverItem();

        return view('media.albums.edit', compact('album', 'parentAlbums', 'coverItems', 'currentCover'));
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
    // ── Couverture ───────────────────────────────────────────────────────────

    /**
     * Définit un item comme couverture de l'album.
     * Accessible aux admins uniquement (canAdmin).
     * Un item null remet la couverture automatique (premier item).
     */
    public function setCover(MediaAlbum $album, \App\Models\Tenant\MediaItem $item): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('manage', $album);

        // Vérifier que l'item appartient bien à cet album
        abort_if($item->album_id !== $album->id, 404);

        // Seules les images peuvent être couverture
        abort_if(! $item->isImage(), 422, 'Seules les images peuvent être utilisées comme couverture.');

        $album->update(['cover_item_id' => $item->id]);

        /** @var \App\Models\Tenant\User $user */
        $user = auth()->user();
        $this->audit->log('media.album.cover_set', $user, [
            'new' => ['album_id' => $album->id, 'item_id' => $item->id, 'item_name' => $item->file_name],
        ]);

        return back()->with('success', '« '.$item->file_name." » définie comme couverture de l'album.");
    }

    /**
     * Réinitialise la couverture — repasse en mode automatique (premier item).
     */
    public function resetCover(MediaAlbum $album): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('manage', $album);

        $album->update(['cover_item_id' => null]);

        /** @var \App\Models\Tenant\User $user */
        $user = auth()->user();
        $this->audit->log('media.album.cover_reset', $user, [
            'new' => ['album_id' => $album->id],
        ]);

        return back()->with('success', 'Couverture réinitialisée — première image de l\'album utilisée automatiquement.');
    }

    /**
     * Déplace un album dans la hiérarchie (change son parent_id).
     * Renomme physiquement le dossier sur le NAS et met à jour
     * tous les nas_path descendants + file_path des médias.
     */
    public function moveAlbum(Request $request, MediaAlbum $album): JsonResponse
    {
        $this->authorize('manage', $album);

        $validated = $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:tenant.media_albums,id'],
        ]);

        $newParentId = isset($validated['parent_id']) ? (int) $validated['parent_id'] : null;

        // Interdire de se mettre soi-même comme parent
        if ($newParentId === $album->id) {
            return response()->json(['error' => 'Un album ne peut pas être son propre parent.'], 422);
        }

        // Interdire les boucles circulaires
        if ($newParentId !== null && in_array($newParentId, $album->descendantIds(), true)) {
            return response()->json(['error' => 'Impossible de déplacer un album dans l\'un de ses descendants.'], 422);
        }

        // Déjà à la bonne position
        if ($album->parent_id === $newParentId) {
            return response()->json(['ok' => true, 'message' => 'Aucun changement.']);
        }

        // ── Calcul du nouveau nas_path ────────────────────────────────────────
        $newParent = $newParentId ? MediaAlbum::find($newParentId) : null;
        $slug = $album->nas_path ? basename($album->nas_path) : \Illuminate\Support\Str::slug($album->name);
        $oldNasPath = $album->nas_path;
        $newNasPath = $newParent?->nas_path
            ? rtrim($newParent->nas_path, '/').'/'.$slug
            : $slug;

        // ── Déplacement physique NAS + mise à jour DB sous le même verrou ───────
        // Le verrou est maintenu jusqu'à la fin de la transaction DB pour éviter
        // qu'une sync NAS s'intercale entre le renommage physique et la mise à jour
        // des file_path en base (ce qui provoquerait des faux doublons SHA-256).
        $lockKey = 'nas_sync_lock_'.md5(config('database.connections.tenant.database', 'tenant'));
        $lock = Cache::lock($lockKey, 120);

        if (! $lock->get()) {
            return response()->json(['error' => 'Synchronisation NAS en cours, réessayez dans quelques secondes.'], 409);
        }

        try {
            if ($oldNasPath && $newNasPath !== $oldNasPath) {
                $nas = app(NasManager::class)->photoDriver();
                if ($nas->exists($oldNasPath)) {
                    $nas->moveDir($oldNasPath, $newNasPath);
                }
            }

            // ── Mise à jour DB dans une transaction ───────────────────────────
            DB::transaction(function () use ($album, $newParentId, $oldNasPath, $newNasPath): void {
                $album->update(['parent_id' => $newParentId, 'nas_path' => $newNasPath]);

                if ($oldNasPath && $newNasPath !== $oldNasPath) {
                    $prefix = $oldNasPath.'/';
                    $newPrefix = $newNasPath.'/';

                    // Descendants : réécrire leur nas_path
                    MediaAlbum::where('nas_path', 'like', $prefix.'%')
                        ->each(function (MediaAlbum $desc) use ($prefix, $newPrefix): void {
                            $desc->update([
                                'nas_path' => $newPrefix.substr($desc->nas_path, strlen($prefix)),
                            ]);
                        });

                    // Médias de l'album ET descendants : réécrire file_path + thumb_path
                    $allIds = array_merge([$album->id], $album->descendantIds());
                    MediaItem::whereIn('album_id', $allIds)
                        ->each(function (MediaItem $item) use ($prefix, $newPrefix): void {
                            $updates = [];
                            if ($item->file_path && str_starts_with($item->file_path, $prefix)) {
                                $updates['file_path'] = $newPrefix.substr($item->file_path, strlen($prefix));
                            }
                            if ($item->thumb_path && str_starts_with($item->thumb_path, $prefix)) {
                                $updates['thumb_path'] = $newPrefix.substr($item->thumb_path, strlen($prefix));
                            }
                            if ($updates) {
                                $item->update($updates);
                            }
                        });
                }
            });

            return response()->json(['ok' => true, 'new_nas_path' => $newNasPath]);
        } finally {
            $lock->release();
        }
    }

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
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', '%'.$q.'%')
                    ->orWhere('nas_path', 'like', '%'.$q.'%');
            })
            ->withCount('items')
            ->with('parent:id,name', 'coverItem')
            ->orderBy('name')
            ->limit(30)
            ->get();

        return response()->json(
            $albums->map(fn ($album) => [
                'id' => $album->id,
                'name' => $album->name,
                'path' => $album->nas_path ?? ($album->parent ? $album->parent->name.' / '.$album->name : null),
                'items_count' => $album->items_count,
                'url' => route('media.albums.show', $album),
                'thumb_url' => $album->coverItem
                    ? route('media.items.serve', [$album->id, $album->coverItem->id, 'thumb'])
                    : null,
            ])
        );
    }

    /**
     * Génère et télécharge un ZIP de tous les fichiers de l'album.
     * Limité à 500 Mo — au-delà, l'utilisateur est redirigé avec un message d'erreur.
     */
    public function exportZip(MediaAlbum $album)
    {
        $this->authorize('download', $album);

        $items = \App\Models\Tenant\MediaItem::where('album_id', $album->id)
            ->whereNull('deleted_at')
            ->orderBy('file_name')
            ->get();

        if ($items->isEmpty()) {
            return back()->with('error', 'Cet album ne contient aucun fichier à exporter.');
        }

        $limitBytes = 500 * 1024 * 1024;
        $totalBytes = $items->sum('file_size_bytes');
        if ($totalBytes > $limitBytes) {
            $totalMb = round($totalBytes / 1048576, 1);

            return back()->with('error', "L'album fait {$totalMb} Mo. L'export ZIP est limité à 500 Mo.");
        }

        set_time_limit(300);

        $nas = $this->nasManager->photoDriver();
        $slug = \Illuminate\Support\Str::slug($album->name) ?: 'album';
        $zipName = $slug.'-'.now()->format('Ymd').'.zip';
        $tmpZip = sys_get_temp_dir().'/phzip_'.uniqid().'.zip';

        $zip = new \ZipArchive;
        if ($zip->open($tmpZip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Impossible de créer l\'archive ZIP.');
        }

        // Fichiers temporaires sur disque — un seul fichier en mémoire à la fois
        $tmpFiles = [];
        foreach ($items as $item) {
            try {
                $contents = $nas->readFile($item->file_path);
                $tmpFile = sys_get_temp_dir().'/phzip_item_'.uniqid().'.tmp';
                file_put_contents($tmpFile, $contents);
                unset($contents); // libère la mémoire immédiatement

                // Évite les collisions de noms dans le ZIP
                $name = $item->file_name;
                $counter = 1;
                while ($zip->locateName($name) !== false) {
                    $ext = pathinfo($item->file_name, PATHINFO_EXTENSION);
                    $base = pathinfo($item->file_name, PATHINFO_FILENAME);
                    $name = $base.'_'.$counter.($ext ? '.'.$ext : '');
                    $counter++;
                }

                $zip->addFile($tmpFile, $name); // référence lazy, pas encore lu
                $tmpFiles[] = $tmpFile;
            } catch (\Throwable) {
                // Fichier inaccessible sur le NAS → on l'ignore silencieusement
            }
        }

        $zip->close(); // libzip lit les fichiers tmp un par un, pas tout en mémoire

        foreach ($tmpFiles as $f) {
            @unlink($f);
        }

        $size = filesize($tmpZip);

        return response()->streamDownload(function () use ($tmpZip) {
            $fh = fopen($tmpZip, 'rb');
            while (! feof($fh)) {
                echo fread($fh, 65536);
                flush();
            }
            fclose($fh);
            @unlink($tmpZip);
        }, $zipName, [
            'Content-Type' => 'application/zip',
            'Content-Length' => $size,
        ]);
    }

    /**
     * Retourne les enfants directs d'un album (JSON) — pour l'arbre lazy-load.
     */
    public function children(MediaAlbum $album): \Illuminate\Http\JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $this->authorize('view', $album);

        $children = MediaAlbum::visibleFor($user)
            ->where('parent_id', $album->id)
            ->withCount(['items', 'children'])
            ->with('coverItem')
            ->orderBy('name')
            ->get();

        return response()->json($children->map(fn ($a) => [
            'id' => $a->id,
            'name' => $a->name,
            'nas_path' => $a->nas_path,
            'items_count' => $a->items_count,
            'has_children' => $a->children_count > 0,
            'url' => route('media.albums.show', $a),
            'thumb_url' => $a->coverItem
                ? route('media.items.serve', [$a->id, $a->coverItem->id, 'thumb'])
                : null,
        ]));
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
        return MediaAlbum::visibleFor($user)
            ->whereNull('parent_id')
            ->withCount(['items', 'children'])
            ->with('coverItem')
            ->orderBy('name')
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
