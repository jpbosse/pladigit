<?php

namespace App\Services;

use App\Models\Tenant\MediaAlbum;
use App\Models\Tenant\MediaItem;
use App\Models\Tenant\User;
use App\Services\Nas\NasConnectorInterface;
use App\Services\Nas\NasManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * MediaService — orchestre NasManager + modèles Eloquent.
 *
 * Responsabilités :
 *   - Upload d'un fichier depuis le navigateur → NAS local → MediaItem
 *   - Génération de miniature (GD, natif PHP)
 *   - Extraction des métadonnées EXIF
 *   - Synchronisation NAS → BDD (détection nouveaux fichiers)
 *   - Vérification SHA-256 (sync complète quotidienne)
 */
class MediaService
{
    /**
     * Extensions supportées et leur mime type attendu.
     *
     * @var array<string, string>
     */
    private const ALLOWED_EXTENSIONS = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'webp' => 'image/webp',
        'gif'  => 'image/gif',
        'tiff' => 'image/tiff',
        'tif'  => 'image/tiff',
        'mp4'  => 'video/mp4',
        'mov'  => 'video/quicktime',
        'avi'  => 'video/x-msvideo',
        'mkv'  => 'video/x-matroska',
        'pdf'  => 'application/pdf',
    ];

    /** Taille max des miniatures en pixels. */
    private const THUMB_MAX_SIZE = 300;

    public function __construct(
        private readonly NasManager $nasManager,
        private readonly AuditService $auditService,
    ) {}

    // =========================================================================
    // Upload
    // =========================================================================

    /**
     * Upload un fichier depuis le navigateur vers le NAS et crée le MediaItem.
     *
     * @throws RuntimeException si le fichier est invalide ou l'upload échoue
     */
    public function upload(UploadedFile $file, MediaAlbum $album, User $uploader): MediaItem
    {
        $this->assertAllowed($file);

        $nas        = $this->nasManager->driver();
        $nasPath    = $this->buildNasPath($album, $file->getClientOriginalName());
        $contents   = file_get_contents($file->getRealPath());

        if ($contents === false) {
            throw new RuntimeException('Impossible de lire le fichier uploadé.');
        }

        // Vérification de doublon par SHA-256 avant l'écriture
        $sha256 = hash('sha256', $contents);
        $existing = MediaItem::where('album_id', $album->id)
            ->where('sha256_hash', $sha256)
            ->first();

        if ($existing) {
            throw new RuntimeException(
                "Ce fichier est un doublon de « {$existing->file_name} » (SHA-256 identique)."
            );
        }

        // Écriture sur le NAS
        if (! $nas->writeFile($nasPath, $contents)) {
            throw new RuntimeException("Échec de l'écriture sur le NAS : {$nasPath}");
        }

        // Miniature (images uniquement)
        $thumbPath = null;
        if (str_starts_with($file->getMimeType() ?? '', 'image/')) {
            $thumbPath = $this->generateThumbnail($contents, $nasPath, $nas);
        }

        // Extraction EXIF (JPEG/TIFF uniquement)
        $exifData = $this->extractExif($file->getRealPath(), $file->getMimeType() ?? '');

        // Dimensions
        [$width, $height] = $this->getImageDimensions($file->getRealPath(), $file->getMimeType() ?? '');

        $item = MediaItem::create([
            'album_id'        => $album->id,
            'uploaded_by'     => $uploader->id,
            'file_name'       => $file->getClientOriginalName(),
            'file_path'       => $nasPath,
            'thumb_path'      => $thumbPath,
            'mime_type'       => $file->getMimeType() ?? 'application/octet-stream',
            'file_size_bytes' => strlen($contents),
            'width_px'        => $width,
            'height_px'       => $height,
            'exif_data'       => $exifData ?: null,
            'sha256_hash'     => $sha256,
            'caption'         => null,
        ]);

        $this->auditService->log('media.upload', $uploader, [
            'model_type' => MediaItem::class,
            'model_id'   => $item->id,
            'new'        => ['file_name' => $item->file_name, 'album_id' => $album->id],
        ]);

        return $item;
    }

    // =========================================================================
    // Synchronisation NAS → BDD
    // =========================================================================

    /**
     * Synchronisation légère par mtime/taille (niveau 1 — toutes les heures).
     * Détecte les nouveaux fichiers dans le répertoire racine du NAS.
     *
     * @return array{added: int, skipped: int}
     */
    public function syncByMtime(MediaAlbum $album, string $nasDirectory = ''): array
    {
        $nas   = $this->nasManager->driver();
        $files = $nas->listFiles($nasDirectory);
        $added = 0;
        $skipped = 0;

        foreach ($files as $entry) {
            if ($entry['type'] !== 'file') {
                continue;
            }

            if (! $this->isExtensionAllowed($entry['name'])) {
                $skipped++;
                continue;
            }

            $existing = MediaItem::where('album_id', $album->id)
                ->where('file_path', $entry['path'])
                ->first();

            if ($existing) {
                // Fichier déjà connu — vérifier si modifié par mtime
                if ($existing->updated_at->timestamp < $entry['mtime']) {
                    Log::info('MediaService::syncByMtime — fichier modifié détecté', [
                        'path' => $entry['path'],
                    ]);
                }
                $skipped++;
                continue;
            }

            // Nouveau fichier → ingestion légère (sans lecture complète)
            $this->ingestNasFile($entry, $album, $nas);
            $added++;
        }

        return compact('added', 'skipped');
    }

    /**
     * Synchronisation complète par SHA-256 (niveau 2 — quotidienne à 23h30).
     * Garantit l'intégrité sans faux négatifs.
     *
     * @return array{updated: int, unchanged: int}
     */
    public function syncBySha256(MediaAlbum $album, string $nasDirectory = ''): array
    {
        $nas     = $this->nasManager->driver();
        $files   = $nas->listFiles($nasDirectory);
        $updated = 0;
        $unchanged = 0;

        foreach ($files as $entry) {
            if ($entry['type'] !== 'file') {
                continue;
            }

            $existing = MediaItem::where('album_id', $album->id)
                ->where('file_path', $entry['path'])
                ->first();

            if (! $existing) {
                continue; // Géré par syncByMtime
            }

            $currentHash = $nas->sha256($entry['path']);

            if ($existing->sha256_hash !== $currentHash) {
                $existing->update(['sha256_hash' => $currentHash]);
                Log::info('MediaService::syncBySha256 — hash modifié', [
                    'path'     => $entry['path'],
                    'item_id'  => $existing->id,
                ]);
                $updated++;
            } else {
                $unchanged++;
            }
        }

        return compact('updated', 'unchanged');
    }

    // =========================================================================
    // Miniatures
    // =========================================================================

    /**
     * Génère une miniature JPEG via GD et l'écrit sur le NAS.
     * Retourne le chemin NAS de la miniature, ou null en cas d'échec.
     */
    public function generateThumbnail(string $contents, string $originalPath, NasConnectorInterface $nas): ?string
    {
        try {
            $src = imagecreatefromstring($contents);

            if ($src === false) {
                return null;
            }

            $origW = imagesx($src);
            $origH = imagesy($src);

            [$newW, $newH] = $this->thumbDimensions($origW, $origH);

            $thumb = imagecreatetruecolor($newW, $newH);

            if ($thumb === false) {
                imagedestroy($src);
                return null;
            }

            // Préserver la transparence (PNG)
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);

            imagecopyresampled($thumb, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

            // Capture en buffer mémoire
            ob_start();
            imagejpeg($thumb, null, 85);
            $thumbContents = ob_get_clean();

            imagedestroy($src);
            imagedestroy($thumb);

            if (! $thumbContents) {
                return null;
            }

            $thumbPath = $this->buildThumbPath($originalPath);
            $nas->writeFile($thumbPath, $thumbContents);

            return $thumbPath;
        } catch (\Throwable $e) {
            Log::warning('MediaService::generateThumbnail — échec', [
                'path'  => $originalPath,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    // =========================================================================
    // EXIF
    // =========================================================================

    /**
     * Extrait les métadonnées EXIF d'un fichier image (JPEG/TIFF uniquement).
     *
     * @return array<string, mixed>
     */
    public function extractExif(string $filePath, string $mimeType): array
    {
        if (! in_array($mimeType, ['image/jpeg', 'image/tiff', 'image/tif'])) {
            return [];
        }

        if (! function_exists('exif_read_data')) {
            return [];
        }

        try {
            $raw = @exif_read_data($filePath, 'ANY_TAG', false);

            if (! is_array($raw)) {
                return [];
            }

            // Sérialiser uniquement les champs utiles et scalaires
            return $this->sanitizeExif($raw);
        } catch (\Throwable) {
            return [];
        }
    }

    // =========================================================================
    // Helpers privés
    // =========================================================================

    /**
     * Construit le chemin NAS de destination pour un upload.
     * Ex : albums/42/2026/04/mon-fichier.jpg
     */
    private function buildNasPath(MediaAlbum $album, string $originalName): string
    {
        $date      = now()->format('Y/m');
        $safe      = Str::slug(pathinfo($originalName, PATHINFO_FILENAME));
        $ext       = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $unique    = Str::random(8);

        return "albums/{$album->id}/{$date}/{$safe}-{$unique}.{$ext}";
    }

    /**
     * Construit le chemin de la miniature depuis le chemin original.
     * Ex : albums/42/2026/04/thumbs/mon-fichier-XXXX.jpg
     */
    private function buildThumbPath(string $originalPath): string
    {
        $dir  = dirname($originalPath);
        $base = pathinfo($originalPath, PATHINFO_FILENAME);

        return "{$dir}/thumbs/{$base}_thumb.jpg";
    }

    /**
     * Calcule les dimensions de la miniature en conservant le ratio.
     *
     * @return array{int, int}
     */
    private function thumbDimensions(int $origW, int $origH): array
    {
        $max = self::THUMB_MAX_SIZE;

        if ($origW <= $max && $origH <= $max) {
            return [$origW, $origH];
        }

        if ($origW > $origH) {
            return [$max, (int) round($origH * $max / $origW)];
        }

        return [(int) round($origW * $max / $origH), $max];
    }

    /**
     * Ingère un fichier NAS existant sans relire son contenu (sync légère).
     *
     * @param  array{name: string, path: string, size: int, mtime: int, type: string}  $entry
     */
    private function ingestNasFile(array $entry, MediaAlbum $album, NasConnectorInterface $nas): void
    {
        $ext      = strtolower(pathinfo($entry['name'], PATHINFO_EXTENSION));
        $mimeType = self::ALLOWED_EXTENSIONS[$ext] ?? 'application/octet-stream';

        MediaItem::create([
            'album_id'        => $album->id,
            'uploaded_by'     => $album->created_by,
            'file_name'       => $entry['name'],
            'file_path'       => $entry['path'],
            'thumb_path'      => null,
            'mime_type'       => $mimeType,
            'file_size_bytes' => $entry['size'],
            'width_px'        => null,
            'height_px'       => null,
            'exif_data'       => null,
            'caption'         => null,
            'sha256_hash'     => null, // Calculé lors de la sync SHA-256
        ]);
    }

    /**
     * Vérifie que le fichier uploadé est autorisé (extension + mime).
     */
    private function assertAllowed(UploadedFile $file): void
    {
        $ext = strtolower($file->getClientOriginalExtension());

        if (! isset(self::ALLOWED_EXTENSIONS[$ext])) {
            throw new RuntimeException("Extension non autorisée : .{$ext}");
        }

        $mime = $file->getMimeType() ?? '';
        $allowed = config('nas.allowed_mimes', []);

        if (! empty($allowed) && ! in_array($mime, $allowed)) {
            throw new RuntimeException("Type MIME non autorisé : {$mime}");
        }

        $maxSize = (int) config('nas.max_file_size', 200 * 1024 * 1024);

        if ($file->getSize() > $maxSize) {
            $mb = round($maxSize / 1_048_576);
            throw new RuntimeException("Fichier trop volumineux (max {$mb} Mo).");
        }
    }

    /**
     * Vérifie si une extension est dans la liste autorisée.
     */
    private function isExtensionAllowed(string $filename): bool
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return isset(self::ALLOWED_EXTENSIONS[$ext]);
    }

    /**
     * Retourne les dimensions [width, height] d'une image, ou [null, null].
     *
     * @return array{int|null, int|null}
     */
    private function getImageDimensions(string $path, string $mimeType): array
    {
        if (! str_starts_with($mimeType, 'image/')) {
            return [null, null];
        }

        $info = @getimagesize($path);

        return $info ? [(int) $info[0], (int) $info[1]] : [null, null];
    }

    /**
     * Conserve uniquement les champs EXIF scalaires et utiles.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function sanitizeExif(array $raw): array
    {
        $keep = [
            'DateTime', 'DateTimeOriginal', 'DateTimeDigitized',
            'Make', 'Model', 'Software',
            'ExposureTime', 'FNumber', 'ISOSpeedRatings',
            'FocalLength', 'Flash',
            'GPSLatitude', 'GPSLatitudeRef',
            'GPSLongitude', 'GPSLongitudeRef',
            'GPSAltitude', 'GPSAltitudeRef',
            'Orientation',
            'ImageWidth', 'ImageLength',
        ];

        $result = [];

        foreach ($keep as $key) {
            if (! isset($raw[$key])) {
                continue;
            }

            $value = $raw[$key];

            // Convertir les fractions GPS (ex: "48/1") en float
            if (is_array($value)) {
                $value = array_map(function ($v) {
                    if (is_string($v) && str_contains($v, '/')) {
                        [$num, $den] = explode('/', $v);
                        return $den != 0 ? (float) $num / (float) $den : 0.0;
                    }
                    return is_scalar($v) ? $v : null;
                }, $value);
                $value = array_filter($value, fn ($v) => $v !== null);
            }

            if (is_scalar($value) || is_array($value)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
