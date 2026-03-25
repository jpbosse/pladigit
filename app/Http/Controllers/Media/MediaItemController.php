<?php

namespace App\Http\Controllers\Media;

use App\Exceptions\DuplicateMediaException;
use App\Http\Controllers\Controller;
use App\Models\Tenant\MediaAlbum;
use App\Models\Tenant\MediaItem;
use App\Models\Tenant\TenantSettings;
use App\Models\Tenant\User;
use App\Services\MediaService;
use App\Services\Nas\NasManager;
use App\Services\WatermarkService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        $this->authorize('upload', $album);

        /** @var view-string $viewName */
        $viewName = 'media.items.create';

        return view($viewName, compact('album'));
    }

    /**
     * Upload d'un ou plusieurs fichiers.
     */
    public function store(Request $request, MediaAlbum $album)
    {
        $this->authorize('upload', $album);

        $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:20'],
            'files.*' => ['file', 'max:204800'], // 200 Mo par fichier
            'force_names' => ['sometimes', 'array'],  // noms des fichiers à forcer malgré doublon
            'force_names.*' => ['string'],
        ]);

        /** @var User $user */
        $user = auth()->user();

        // ── Pré-vérification quota ────────────────────────────────────────────
        $org = app(\App\Services\TenantManager::class)->current();
        $quotaMb = $org !== null ? ($org->storage_quota_mb ?? 10240) : 10240;
        $quotaBytes = $quotaMb * 1024 * 1024;
        $usedBytes = (int) \App\Models\Tenant\MediaItem::on('tenant')->whereNull('deleted_at')->sum('file_size_bytes');
        $freeBytes = max(0, $quotaBytes - $usedBytes);
        $totalIncoming = (int) array_sum(array_map(
            fn ($f) => $f->getSize(),
            $request->file('files', [])
        ));

        if ($freeBytes === 0 || $totalIncoming > $freeBytes) {
            $freeMb = round($freeBytes / 1048576, 1);
            $incomingMb = round($totalIncoming / 1048576, 1);
            $msg = $freeBytes === 0
                ? "Quota de stockage atteint ({$quotaMb} Mo). Aucun fichier importé. Contactez votre administrateur."
                : "Les fichiers sélectionnés ({$incomingMb} Mo) dépassent l'espace disponible ({$freeMb} Mo / {$quotaMb} Mo).";

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['error' => $msg], 422);
            }

            return redirect()->route('media.albums.show', $album)->with('error', $msg);
        }

        $forceNames = $request->input('force_names', []);
        $success = 0;
        $errors = [];
        $duplicates = []; // fichiers en attente de confirmation

        foreach ($request->file('files', []) as $file) {
            $name = $file->getClientOriginalName();
            $force = in_array($name, $forceNames);

            try {
                $this->mediaService->upload($file, $album, $user, $force);
                $success++;
            } catch (DuplicateMediaException $e) {
                // Retourner les infos pour la modale de confirmation côté JS
                $duplicates[] = [
                    'file_name' => $name,
                    'original_file_name' => $e->originalFileName,
                    'original_album_name' => $e->originalAlbumName,
                    'same_album' => $e->sameAlbum,
                ];
            } catch (\RuntimeException $e) {
                $errors[] = $name.': '.$e->getMessage();
            }
        }

        // ── Réponse JSON (upload AJAX) ────────────────────────────────────────
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => $success,
                'errors' => $errors,
                'duplicates' => $duplicates,
            ]);
        }

        // ── Réponse redirect (formulaire classique) ───────────────────────────
        $message = "{$success} fichier(s) importé(s) avec succès.";

        return redirect()
            ->route('media.albums.show', $album)
            ->with('success', $message)
            ->with('upload_errors', $errors)
            ->with('upload_duplicates', $duplicates);
    }

    /**
     * Import d'un fichier ZIP vers un album.
     * Le ZIP est extrait en arrière-plan via la queue.
     */
    public function importZip(Request $request, MediaAlbum $album)
    {
        $this->authorize('upload', $album);

        $request->validate([
            'zip_file' => ['required', 'file', 'mimes:zip', 'max:512000'], // 500 Mo max
        ]);

        /** @var User $user */
        $user = auth()->user();

        $org = app(\App\Services\TenantManager::class)->current();
        $slug = $org->slug;

        // Stocker le ZIP temporairement

        // Créer le dossier si nécessaire et stocker le ZIP
        \Storage::makeDirectory('tmp/zip_imports');
        $path = $request->file('zip_file')->store('tmp/zip_imports');

        // Dispatcher le Job
        \App\Jobs\ProcessZipImport::dispatch($path, $album->id, $user->id, $slug);

        return redirect()
            ->route('media.albums.show', $album)
            ->with('success', 'Import ZIP lancé en arrière-plan. Les photos apparaîtront progressivement dans l\'album.');
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
     * Rotation d'une image (90°, 180°, 270°) — réécrit le fichier sur le NAS.
     */
    public function rotate(Request $request, MediaAlbum $album, MediaItem $item): \Illuminate\Http\JsonResponse
    {
        $this->authorize('manage', $album);
        $this->assertBelongsToAlbum($item, $album);

        $degrees = (int) $request->input('degrees', 90);
        if (! in_array($degrees, [90, 180, 270], true)) {
            return response()->json(['error' => 'Degrés invalides (90, 180 ou 270)'], 422);
        }

        $nas = app(NasManager::class)->photoDriver();
        $contents = $nas->readFile($item->file_path);
        $src = @imagecreatefromstring($contents);

        if (! $src) {
            return response()->json(['error' => 'Format non supporté'], 422);
        }

        // GD tourne dans le sens anti-horaire — on inverse pour avoir le sens horaire
        $rotated = imagerotate($src, 360 - $degrees, 0);
        imagedestroy($src);

        ob_start();
        $mime = $item->mime_type ?? 'image/jpeg';
        if ($mime === 'image/png') {
            imagepng($rotated, null, 6);
        } else {
            imagejpeg($rotated, null, 92);
        }
        $output = ob_get_clean();
        imagedestroy($rotated);

        $nas->writeFile($item->file_path, $output);

        // Nouvelles dimensions (permutées pour 90°/270°)
        $swap = in_array($degrees, [90, 270], true);
        $newW = $swap ? $item->height_px : $item->width_px;
        $newH = $swap ? $item->width_px : $item->height_px;

        $thumbPath = app(MediaService::class)->generateThumbnail($output, $item->file_path, $nas);
        $item->update([
            'file_size_bytes' => strlen($output),
            'width_px' => $newW,
            'height_px' => $newH,
            'thumb_path' => $thumbPath ?? $item->thumb_path,
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * Recadrage d'une image — réécrit le fichier sur le NAS.
     */
    public function crop(Request $request, MediaAlbum $album, MediaItem $item): \Illuminate\Http\JsonResponse
    {
        $this->authorize('manage', $album);
        $this->assertBelongsToAlbum($item, $album);

        $request->validate([
            'x' => ['required', 'integer', 'min:0'],
            'y' => ['required', 'integer', 'min:0'],
            'width' => ['required', 'integer', 'min:10'],
            'height' => ['required', 'integer', 'min:10'],
        ]);

        $nas = app(NasManager::class)->photoDriver();
        $contents = $nas->readFile($item->file_path);
        $src = @imagecreatefromstring($contents);

        if (! $src) {
            return response()->json(['error' => 'Format non supporté'], 422);
        }

        $cropped = imagecrop($src, [
            'x' => $request->integer('x'),
            'y' => $request->integer('y'),
            'width' => $request->integer('width'),
            'height' => $request->integer('height'),
        ]);
        imagedestroy($src);

        if (! $cropped) {
            return response()->json(['error' => 'Recadrage hors limites'], 422);
        }

        ob_start();
        $mime = $item->mime_type ?? 'image/jpeg';
        if ($mime === 'image/png') {
            imagepng($cropped, null, 6);
        } else {
            imagejpeg($cropped, null, 92);
        }
        $output = ob_get_clean();
        imagedestroy($cropped);

        $nas->writeFile($item->file_path, $output);

        $thumbPath = app(MediaService::class)->generateThumbnail($output, $item->file_path, $nas);
        $item->update([
            'file_size_bytes' => strlen($output),
            'width_px' => $request->integer('width'),
            'height_px' => $request->integer('height'),
            'thumb_path' => $thumbPath ?? $item->thumb_path,
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * Suppression (soft delete) d'un média.
     */
    public function destroy(MediaAlbum $album, MediaItem $item)
    {
        $this->assertBelongsToAlbum($item, $album);

        // Suppression physique sur le NAS
        try {
            $nas = app(NasManager::class)->photoDriver();
            if ($item->file_path && $nas->exists($item->file_path)) {
                $nas->deleteFile($item->file_path);
            }
            if ($item->thumb_path && $nas->exists($item->thumb_path)) {
                $nas->deleteFile($item->thumb_path);
            }
        } catch (\Throwable $e) {
            // Log mais on continue — on supprime quand même l'entrée BDD
            \Illuminate\Support\Facades\Log::warning('MediaItemController::destroy — suppression NAS échouée', [
                'item_id' => $item->id,
                'file_path' => $item->file_path,
                'error' => $e->getMessage(),
            ]);
        }

        $item->delete();
        // L'Observer MediaItemObserver::deleted() recalcule is_duplicate automatiquement.

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json(['success' => true]);
        }

        return redirect()
            ->route('media.albums.show', $album)
            ->with('success', "Fichier « {$item->file_name} » supprimé.");
    }

    /**
     * Téléchargement du fichier original depuis le NAS.
     * Applique le watermark à la volée si activé pour ce tenant.
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

            // ── Watermark à la volée ───────────────────────────────────────
            $settings = TenantSettings::first();
            $watermark = app(WatermarkService::class);

            if ($settings !== null && $watermark->shouldApply($item->mime_type, $settings)) {
                $contents = $watermark->apply($contents, $item->mime_type, $settings);
                $mimeType = 'image/jpeg'; // WatermarkService sort toujours en JPEG
            } else {
                $mimeType = $item->mime_type;
            }
            // ─────────────────────────────────────────────────────────────

            return response($contents, 200, [
                'Content-Type' => $mimeType,
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

        // Images > seuil configurable : stream() pour éviter de charger tout le fichier en mémoire.
        // Les miniatures (thumb) sont toujours petites — on les sert en mémoire.
        if ($type !== 'thumb' && ($item->file_size_bytes ?? 0) > 0) {
            $settings = TenantSettings::on('tenant')->first();
            $thresholdMb = $settings->media_stream_threshold_mb ?? 10;
            if ($thresholdMb > 0 && $item->file_size_bytes > $thresholdMb * 1024 * 1024) {
                return $this->stream($album, $item, request());
            }
        }

        // Cache-Control : 7 jours pour les thumbnails (ne changent qu'après rotation/crop),
        // 1 jour pour les originaux. ETag + Last-Modified pour les requêtes conditionnelles.
        $isThumb = $type === 'thumb';
        $maxAge = $isThumb ? 604800 : 86400;
        $etag = '"'.$item->updated_at->timestamp.($isThumb ? 't' : 'f').'"';
        $lastModified = $item->updated_at->format('D, d M Y H:i:s').' GMT';

        if (request()->header('If-None-Match') === $etag) {
            return response('', 304, [
                'ETag' => $etag,
                'Cache-Control' => "public, max-age={$maxAge}, must-revalidate",
            ]);
        }

        try {
            $nas = app(NasManager::class)->photoDriver();
            $path = ($isThumb && $item->thumb_path)
                ? $item->thumb_path
                : $item->file_path;

            $contents = $nas->readFile($path);
            $mime = $isThumb ? 'image/jpeg' : $item->mime_type;

            return response($contents, 200, [
                'Content-Type' => $mime,
                'Cache-Control' => "public, max-age={$maxAge}, must-revalidate",
                'ETag' => $etag,
                'Last-Modified' => $lastModified,
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
