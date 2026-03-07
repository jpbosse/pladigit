<?php

namespace App\Http\Controllers\Media;

use App\Http\Controllers\Controller;
use App\Models\Tenant\MediaAlbum;
use App\Models\Tenant\MediaItem;
use App\Models\Tenant\User;
use App\Services\MediaService;
use Illuminate\Http\Request;

/**
 * Gestion des fichiers médias (upload, affichage, suppression, téléchargement).
 */
class MediaItemController extends Controller
{
    public function __construct(
        private readonly MediaService $mediaService,
    ) {}

    /**
     * Formulaire d'upload vers un album.
     */
    public function create(MediaAlbum $album)
    {
        return view('media.items.create', compact('album'));
    }

    /**
     * Upload d'un ou plusieurs fichiers.
     * Appelé depuis le formulaire drag & drop.
     */
    public function store(Request $request, MediaAlbum $album)
    {
        $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:20'],
            'files.*' => ['file', 'max:204800'], // 200 Mo par fichier
        ]);

        /** @var User $user */
        $user = auth()->user();
        $success = 0;
        $errors = [];

        foreach ($request->file('files', []) as $file) {
            try {
                $this->mediaService->upload($file, $album, $user);
                $success++;
            } catch (\RuntimeException $e) {
                $errors[] = $file->getClientOriginalName().': '.$e->getMessage();
            }
        }

        $message = "{$success} fichier(s) importé(s) avec succès.";

        if (! empty($errors)) {
            return redirect()
                ->route('media.albums.show', $album)
                ->with('success', $message)
                ->with('upload_errors', $errors);
        }

        return redirect()
            ->route('media.albums.show', $album)
            ->with('success', $message);
    }

    /**
     * Affichage détaillé d'un média (visionneuse plein écran).
     */
    public function show(MediaAlbum $album, MediaItem $item)
    {
        $this->assertBelongsToAlbum($item, $album);

        $prev = MediaItem::where('album_id', $album->id)
            ->where('id', '<', $item->id)
            ->orderByDesc('id')
            ->first();

        $next = MediaItem::where('album_id', $album->id)
            ->where('id', '>', $item->id)
            ->orderBy('id')
            ->first();

        // Position et total pour le compteur
        $position = MediaItem::where('album_id', $album->id)
            ->where('id', '<=', $item->id)
            ->count();
        $total = MediaItem::where('album_id', $album->id)->count();

        return view('media.items.show', compact('album', 'item', 'prev', 'next', 'position', 'total'));
    }

    /**
     * Met à jour la description (caption) d'un média — appelé en AJAX.
     */
    public function updateCaption(Request $request, MediaAlbum $album, MediaItem $item)
    {
        $this->assertBelongsToAlbum($item, $album);

        $request->validate([
            'caption' => ['nullable', 'string', 'max:500'],
        ]);

        $item->update(['caption' => $request->caption]);

        return response()->json(['ok' => true, 'caption' => $item->caption]);
    }

    /**
     * Suppression (soft delete) d'un média.
     */
    public function destroy(MediaAlbum $album, MediaItem $item)
    {
        $this->assertBelongsToAlbum($item, $album);

        $item->delete();

        return redirect()
            ->route('media.albums.show', $album)
            ->with('success', "Fichier « {$item->file_name} » supprimé.");
    }

    /**
     * Téléchargement du fichier original depuis le NAS.
     */
    public function download(MediaAlbum $album, MediaItem $item)
    {
        $this->assertBelongsToAlbum($item, $album);

        try {
            $nas = app(\App\Services\Nas\NasManager::class)->driver();
            $contents = $nas->readFile($item->file_path);

            return response($contents, 200, [
                'Content-Type' => $item->mime_type,
                'Content-Disposition' => 'attachment; filename="'.$item->file_name.'"',
                'Content-Length' => strlen($contents),
            ]);
        } catch (\RuntimeException $e) {
            abort(404, 'Fichier introuvable sur le NAS : '.$e->getMessage());
        }
    }

    /**
     * Affichage en ligne (inline) du fichier ou de sa miniature.
     * Utilisé pour les balises <img src="..."> dans la galerie.
     */
    public function serve(MediaAlbum $album, MediaItem $item, string $type = 'thumb')
    {
        $this->assertBelongsToAlbum($item, $album);

        try {
            $nas = app(\App\Services\Nas\NasManager::class)->driver();
            $path = ($type === 'thumb' && $item->thumb_path)
                ? $item->thumb_path
                : $item->file_path;

            $contents = $nas->readFile($path);

            return response($contents, 200, [
                'Content-Type' => $item->mime_type,
                'Cache-Control' => 'public, max-age=86400',
            ]);
        } catch (\RuntimeException $e) {
            abort(404);
        }
    }

    // ── Helpers privés ───────────────────────────────────────

    /**
     * Vérifie que le MediaItem appartient bien à l'album de la route.
     */
    private function assertBelongsToAlbum(MediaItem $item, MediaAlbum $album): void
    {
        if ($item->album_id !== $album->id) {
            abort(404);
        }
    }
}
