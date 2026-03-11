<?php

namespace App\Http\Controllers\Media;

use App\Http\Controllers\Controller;
use App\Models\Tenant\MediaAlbum;
use App\Models\Tenant\MediaItem;
use App\Models\Tenant\User;
use App\Services\MediaService;
use App\Services\Nas\NasManager;
use Illuminate\Http\Request;
use Illuminate\Http\StreamedResponse;

/**
 * Gestion des fichiers médias (upload, affichage, suppression, téléchargement, streaming).
 */
class MediaItemController extends Controller
{
    public function __construct(
        private readonly MediaService $mediaService,
    ) {}

    /**
     * Formulaire d'upload vers un album.
     */
    public function create(MediaAlbum $album): \Illuminate\View\View
    {
        /** @var view-string $viewName */
        $viewName = 'media.items.create';

        return view($viewName, compact('album'));
    }

    /**
     * Upload d'un ou plusieurs fichiers.
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
     * Charge le fichier en mémoire — réservé aux fichiers non-vidéo ou petits fichiers.
     */
    public function download(MediaAlbum $album, MediaItem $item)
    {
        $this->assertBelongsToAlbum($item, $album);

        // Pour les vidéos, rediriger vers le streaming avec force-download
        if ($this->isVideo($item->mime_type)) {
            return $this->stream($album, $item, request(), forceDownload: true);
        }

        try {
            $nas = app(NasManager::class)->photoDriver();
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
     * Affichage inline (thumbnails et images).
     * Charge en mémoire — pour miniatures et images (non vidéo).
     */
    public function serve(MediaAlbum $album, MediaItem $item, string $type = 'thumb')
    {
        $this->assertBelongsToAlbum($item, $album);

        // Les vidéos passent toujours par stream()
        if ($this->isVideo($item->mime_type) && $type !== 'thumb') {
            return $this->stream($album, $item, request());
        }

        try {
            $nas = app(NasManager::class)->photoDriver();
            $path = ($type === 'thumb' && $item->thumb_path)
                ? $item->thumb_path
                : $item->file_path;

            $contents = $nas->readFile($path);

            $mime = ($type === 'thumb') ? 'image/jpeg' : $item->mime_type;

            return response($contents, 200, [
                'Content-Type' => $mime,
                'Cache-Control' => 'public, max-age=86400',
            ]);
        } catch (\RuntimeException $e) {
            abort(404);
        }
    }

    /**
     * Streaming avec support Range HTTP (RFC 7233).
     *
     * Utilisé pour :
     *   - Lecture vidéo MP4/MOV dans un <video> (scrubbing, lecture partielle)
     *   - Téléchargement sécurisé reprenant là où il s'est arrêté
     *
     * Le fichier n'est JAMAIS chargé entièrement en mémoire.
     * Chunks de 1 Mo envoyés au fur et à mesure.
     */
    public function stream(
        MediaAlbum $album,
        MediaItem $item,
        Request $request,
        bool $forceDownload = false
    ): StreamedResponse {
        $this->assertBelongsToAlbum($item, $album);

        $nas = app(NasManager::class)->photoDriver();
        $fileSize = $item->file_size_bytes ?? $nas->size($item->file_path);
        $mimeType = $item->mime_type;

        // ── Parsing de l'en-tête Range ────────────────────────────────────────
        $rangeHeader = $request->header('Range');
        [$start, $end] = $this->parseRange($rangeHeader, $fileSize);
        $length = $end - $start + 1;

        $isPartial = $rangeHeader !== null;
        $status = $isPartial ? 206 : 200;

        // ── En-têtes de réponse ───────────────────────────────────────────────
        $headers = [
            'Content-Type' => $mimeType,
            'Content-Length' => $length,
            'Accept-Ranges' => 'bytes',
            'Content-Range' => "bytes {$start}-{$end}/{$fileSize}",
            'Cache-Control' => 'no-cache, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($forceDownload) {
            $headers['Content-Disposition'] = 'attachment; filename="'.$item->file_name.'"';
        } else {
            $headers['Content-Disposition'] = 'inline; filename="'.$item->file_name.'"';
        }

        // ── Réponse streamée ──────────────────────────────────────────────────
        return response()->stream(
            callback: function () use ($nas, $item, $start, $end): void {
                $stream = null;
                $chunkSize = 1024 * 1024; // 1 Mo par chunk
                $cursor = $start;

                try {
                    $stream = $nas->openReadStream($item->file_path);

                    while ($cursor <= $end) {
                        $toRead = min($chunkSize, $end - $cursor + 1);
                        $chunk = $nas->readChunk($stream, $cursor, $toRead);

                        if ($chunk === false) {
                            break;
                        }

                        echo $chunk;

                        if (ob_get_level() > 0) {
                            ob_flush();
                        }
                        flush();

                        $cursor += strlen($chunk);

                        // Abandon si le client a raccroché
                        if (connection_aborted()) {
                            break;
                        }
                    }
                } finally {
                    if ($stream !== null) {
                        $nas->closeReadStream($stream);
                    }
                }
            },
            status: $status,
            headers: $headers,
        );
    }

    // ── Helpers privés ───────────────────────────────────────────────────────

    /**
     * Vérifie que le MediaItem appartient bien à l'album de la route.
     */
    private function assertBelongsToAlbum(MediaItem $item, MediaAlbum $album): void
    {
        if ($item->album_id !== $album->id) {
            abort(404);
        }
    }

    /**
     * Détermine si un MIME type est une vidéo.
     */
    private function isVideo(string $mimeType): bool
    {
        return str_starts_with($mimeType, 'video/');
    }

    /**
     * Parse l'en-tête Range HTTP et retourne [start, end].
     * Supporte le format : bytes=X-Y, bytes=X-, bytes=-Y
     *
     * @return array{int, int}
     */
    private function parseRange(?string $rangeHeader, int $fileSize): array
    {
        $start = 0;
        $end = $fileSize - 1;

        if ($rangeHeader === null || ! str_starts_with($rangeHeader, 'bytes=')) {
            return [$start, $end];
        }

        $range = substr($rangeHeader, 6); // Retire "bytes="

        if (str_contains($range, '-')) {
            [$rawStart, $rawEnd] = explode('-', $range, 2);

            if ($rawStart === '') {
                // bytes=-500 → 500 derniers octets
                $start = max(0, $fileSize - (int) $rawEnd);
            } else {
                $start = (int) $rawStart;
                $end = $rawEnd !== '' ? min((int) $rawEnd, $fileSize - 1) : $fileSize - 1;
            }
        }

        // Sécurisation des bornes
        $start = max(0, min($start, $fileSize - 1));
        $end = max($start, min($end, $fileSize - 1));

        return [$start, $end];
    }
}
