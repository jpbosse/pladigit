<?php

// app/Services/MediaService.php

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
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        'tiff' => 'image/tiff',
        'tif' => 'image/tiff',
        'mp4' => 'video/mp4',
        'mov' => 'video/quicktime',
        'avi' => 'video/x-msvideo',
        'mkv' => 'video/x-matroska',
        'pdf' => 'application/pdf',
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
        $this->assertQuota($file, $uploader);

        $nas = $this->nasManager->driver();
        $nasPath = $this->buildNasPath($album, $file->getClientOriginalName());

        $contents = file_get_contents($file->getRealPath());

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

        // Transaction BDD — créer le MediaItem en statut 'pending'
        // La miniature, l'EXIF et les dimensions sont traités en queue.
        try {
            $item = \DB::connection('tenant')->transaction(function () use (
                $album, $uploader, $file, $nasPath, $contents, $sha256
            ): MediaItem {
                return MediaItem::create([
                    'album_id' => $album->id,
                    'uploaded_by' => $uploader->id,
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $nasPath,
                    'thumb_path' => null,
                    'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                    'file_size_bytes' => strlen($contents),
                    'width_px' => null,
                    'height_px' => null,
                    'exif_data' => null,
                    'sha256_hash' => $sha256,
                    'caption' => null,
                    'processing_status' => 'pending',
                ]);
            });

        } catch (\Throwable $e) {
            // Compensation : supprimer les fichiers NAS pour éviter les orphelins
            try {
                if ($nas->exists($nasPath)) {
                    $nas->deleteFile($nasPath);
                }
                if ($thumbPath && $nas->exists($thumbPath)) {
                    $nas->deleteFile($thumbPath);
                }
            } catch (\Throwable) {
                // Log mais on ne masque pas l'erreur originale
                \Illuminate\Support\Facades\Log::warning('MediaService::upload — impossible de supprimer le fichier NAS orphelin', [
                    'nas_path' => $nasPath,
                    'thumb_path' => $thumbPath,
                ]);
            }

            throw new RuntimeException(
                "Échec de l'enregistrement en base après écriture NAS : ".$e->getMessage(),
                previous: $e
            );
        }

        // Dispatch du Job de traitement en arrière-plan
        $org = app(\App\Services\TenantManager::class)->current();
        \App\Jobs\ProcessMediaUpload::dispatch(
            $item->id,
            $org->slug,
            $nasPath,
            $item->mime_type,
        );

        $this->auditService->log('media.upload', $uploader, [
            'model_type' => MediaItem::class,
            'model_id' => $item->id,
            'new' => ['file_name' => $item->file_name, 'album_id' => $album->id],
        ]);

        return $item;
    }

    // =========================================================================
    /**
     * Supprime physiquement sur le NAS tous les fichiers d'un album et de ses sous-albums.
     * Appelé avant le soft-delete de l'album en base.
     */
    public function deleteAlbumFiles(MediaAlbum $album): void
    {
        $nas = $this->nasManager->photoDriver();

        // Traiter récursivement les sous-albums d'abord
        foreach ($album->children as $child) {
            $this->deleteAlbumFiles($child);
        }

        // Supprimer tous les fichiers + thumbs de cet album
        $album->items()->withTrashed()->each(function (MediaItem $item) use ($nas) {
            try {
                if ($item->file_path && $nas->exists($item->file_path)) {
                    $nas->deleteFile($item->file_path);
                }
                if ($item->thumb_path && $nas->exists($item->thumb_path)) {
                    $nas->deleteFile($item->thumb_path);
                }
            } catch (\Throwable $e) {
                Log::warning('MediaService::deleteAlbumFiles — suppression NAS échouée', [
                    'item_id' => $item->id,
                    'file_path' => $item->file_path,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }

    // Synchronisation arborescence NAS → albums/sous-albums
    // =========================================================================

    /**
     * Synchronise récursivement l'arborescence NAS vers la hiérarchie albums/sous-albums.
     *
     * Comportement :
     *   - Chaque dossier NAS → un album (créé s'il n'existe pas, nas_path = chemin NAS)
     *   - Les sous-dossiers → des sous-albums (parent_id = album parent)
     *   - Les fichiers dans chaque dossier → ingérés via syncByMtime ou syncBySha256
     *   - Les albums existants (nas_path correspondant) ne sont pas recréés
     *   - Les albums créés manuellement (nas_path = null) sont ignorés
     *
     * @param  string  $nasRoot  Chemin racine NAS à parcourir ('' = racine)
     * @param  User|null  $owner  Propriétaire des albums créés (null = premier admin)
     * @param  bool  $deep  true = sync SHA-256, false = sync mtime
     * @return array{albums_created: int, albums_found: int, albums_removed: int, files_added: int, files_skipped: int, files_removed: int, errors: int}
     */
    public function syncAlbumTree(
        string $nasRoot = '',
        ?User $owner = null,
        bool $deep = false,
    ): array {
        $nas = $this->nasManager->photoDriver();

        $stats = [
            'albums_created' => 0,
            'albums_found' => 0,
            'albums_removed' => 0,
            'files_added' => 0,
            'files_skipped' => 0,
            'files_removed' => 0,
            'errors' => 0,
        ];

        // Depuis la racine, on descend directement dans les sous-dossiers
        // sans créer d'album racine — chaque dossier de premier niveau = album racine
        try {
            $topDirs = $nas->listDirectories($nasRoot);
        } catch (\Throwable $e) {
            Log::error('MediaService::syncAlbumTree — erreur listDirectories racine', [
                'error' => $e->getMessage(),
            ]);
            $stats['errors']++;

            return $stats;
        }

        foreach ($topDirs as $dir) {
            // Ignorer le dossier albums/ — c'est la racine des uploads via l'interface,
            // pas un dossier créé par les utilisateurs sur le NAS.
            if ($dir['name'] === 'albums') {
                continue;
            }
            $this->syncDirectory($nas, $dir['path'], null, $owner, $deep, $stats);
        }

        // Purge globale des fichiers : supprime tous les items dont le fichier n'existe plus sur le NAS,
        // y compris ceux appartenant à des dossiers entièrement supprimés.
        try {
            $removed = $this->purgeAllOrphanItems($nas);
            $stats['files_removed'] += $removed;
        } catch (\Throwable $e) {
            Log::error('MediaService::syncAlbumTree — erreur purgeAllOrphanItems', [
                'error' => $e->getMessage(),
            ]);
            $stats['errors']++;
        }

        // Purge globale des albums NAS : supprime les albums liés à un dossier NAS
        // qui n'existe plus, à condition qu'ils soient vides.
        try {
            $albumsRemoved = $this->purgeOrphanAlbums($nas);
            $stats['albums_removed'] += $albumsRemoved;
        } catch (\Throwable $e) {
            Log::error('MediaService::syncAlbumTree — erreur purgeOrphanAlbums', [
                'error' => $e->getMessage(),
            ]);
            $stats['errors']++;
        }

        return $stats;
    }

    /**
     * Parcourt récursivement un dossier NAS et crée/met à jour l'album correspondant.
     */
    private function syncDirectory(
        NasConnectorInterface $nas,
        string $nasPath,
        ?int $parentId,
        ?User $owner,
        bool $deep,
        array &$stats,
    ): void {
        // 1. Trouver ou créer l'album correspondant à ce dossier NAS
        $album = $this->findOrCreateAlbumForPath($nasPath, $parentId, $owner, $stats);

        if ($album === null) {
            $stats['errors']++;

            return;
        }

        // 2. Synchroniser les fichiers de ce dossier
        try {
            if ($deep) {
                $result = $this->syncBySha256($album, $nasPath);
                $stats['files_added'] += $result['updated'];
                $stats['files_skipped'] += $result['unchanged'];
            } else {
                $result = $this->syncByMtime($album, $nasPath);
                $stats['files_added'] += $result['added'];
                $stats['files_skipped'] += $result['skipped'];
            }
        } catch (\Throwable $e) {
            Log::error('MediaService::syncDirectory — erreur sync fichiers', [
                'nas_path' => $nasPath,
                'error' => $e->getMessage(),
            ]);
            $stats['errors']++;
        }

        // Note : la purge des fichiers supprimés est gérée globalement
        // par purgeAllOrphanItems() en fin de syncAlbumTree().

        // 3. Descendre récursivement dans les sous-dossiers
        try {
            $subDirs = $nas->listDirectories($nasPath);
        } catch (\Throwable $e) {
            Log::error('MediaService::syncDirectory — erreur listDirectories', [
                'nas_path' => $nasPath,
                'error' => $e->getMessage(),
            ]);
            $stats['errors']++;

            return;
        }

        foreach ($subDirs as $dir) {
            // Ignorer le dossier thumbs/ — il ne contient que des miniatures générées
            if (basename($dir['path']) === 'thumbs') {
                continue;
            }
            $this->syncDirectory($nas, $dir['path'], $album->id, $owner, $deep, $stats);
        }
    }

    /**
     * Trouve l'album associé à un chemin NAS, ou le crée s'il n'existe pas.
     */
    private function findOrCreateAlbumForPath(
        string $nasPath,
        ?int $parentId,
        ?User $owner,
        array &$stats,
    ): ?MediaAlbum {

        // Chercher un album existant avec ce nas_path ET ce parent_id
        $existing = MediaAlbum::where('nas_path', $nasPath)
            ->where('parent_id', $parentId)
            ->first();

        if ($existing) {
            $stats['albums_found']++;

            return $existing;
        }

        // Créer l'album — nom = dernier segment du chemin NAS
        $name = basename($nasPath);
        $ownerId = $owner !== null ? $owner->id : (User::where('role', 'admin')->value('id') ?? 1);

        try {
            $album = MediaAlbum::create([
                'name' => $name,
                'description' => null,
                'visibility' => 'restricted',
                'parent_id' => $parentId,
                'nas_path' => $nasPath,
                'created_by' => $ownerId,
            ]);

            $stats['albums_created']++;
            Log::info('MediaService::syncAlbumTree — album créé', [
                'name' => $name,
                'nas_path' => $nasPath,
                'parent_id' => $parentId,
            ]);

            return $album;
        } catch (\Throwable $e) {
            Log::error('MediaService::syncAlbumTree — erreur création album', [
                'nas_path' => $nasPath,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
    /**
     * Purge globale — parcourt TOUS les MediaItems du tenant (toutes albums confondus)
     * et supprime ceux dont le fichier n'existe plus sur le NAS.
     *
     * Utilisé en fin de syncAlbumTree pour couvrir le cas où des dossiers entiers
     * ont été supprimés du NAS (syncDirectory ne serait jamais appelé pour eux).
     *
     * @return int Nombre d'items supprimés
     */
    private function purgeAllOrphanItems(NasConnectorInterface $nas): int
    {
        $removed = 0;

        MediaItem::notThumbs()->each(function (MediaItem $item) use ($nas, &$removed) {
            try {
                if (! $nas->exists($item->file_path)) {
                    Log::info('MediaService::purgeAllOrphanItems — fichier absent du NAS, suppression', [
                        'item_id' => $item->id,
                        'file_path' => $item->file_path,
                    ]);
                    $item->delete(); // soft-delete
                    $removed++;
                }
            } catch (\Throwable $e) {
                Log::warning('MediaService::purgeAllOrphanItems — impossible de vérifier existence', [
                    'item_id' => $item->id,
                    'file_path' => $item->file_path,
                    'error' => $e->getMessage(),
                ]);
            }
        });

        return $removed;
    }

    /**
     * Purge les albums liés à un chemin NAS (nas_path non null) dont le dossier
     * n'existe plus sur le NAS ET qui ne contiennent plus aucun item.
     *
     * Les albums créés manuellement (nas_path = null) ne sont jamais touchés.
     * La suppression se fait du plus profond vers la racine pour respecter
     * les contraintes parent_id (enfants supprimés avant parents).
     *
     * @return int Nombre d'albums supprimés
     */
    private function purgeOrphanAlbums(NasConnectorInterface $nas): int
    {
        $removed = 0;

        // Récupère tous les albums NAS, triés du plus profond au moins profond
        // (ceux qui ont un parent_id d'abord) pour supprimer les enfants avant les parents
        $albums = MediaAlbum::whereNotNull('nas_path')
            ->withCount('items')
            ->orderByRaw('parent_id IS NULL ASC') // enfants (parent_id non null) d'abord
            ->get();

        foreach ($albums as $album) {
            try {
                // Ne supprimer que si le dossier NAS n'existe plus
                if ($nas->exists($album->nas_path)) {
                    continue;
                }

                // Recharger le compte d'items (au cas où la purge précédente en aurait supprimé)
                $itemsCount = $album->items()->count();

                if ($itemsCount > 0) {
                    // Des items subsistent (ex: upload manuel) — on ne supprime pas l'album
                    Log::info('MediaService::purgeOrphanAlbums — dossier NAS absent mais album non vide, conservé', [
                        'album_id' => $album->id,
                        'nas_path' => $album->nas_path,
                        'items' => $itemsCount,
                    ]);

                    continue;
                }

                Log::info('MediaService::purgeOrphanAlbums — dossier NAS absent et album vide, suppression', [
                    'album_id' => $album->id,
                    'name' => $album->name,
                    'nas_path' => $album->nas_path,
                ]);

                $album->delete();
                $removed++;

            } catch (\Throwable $e) {
                Log::warning('MediaService::purgeOrphanAlbums — erreur sur album', [
                    'album_id' => $album->id,
                    'nas_path' => $album->nas_path,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $removed;
    }

    // =========================================================================

    /**
     * Synchronisation légère par mtime/taille (niveau 1 — toutes les heures).
     * Détecte les nouveaux fichiers dans le répertoire racine du NAS.
     *
     * @return array{added: int, skipped: int}
     */
    public function syncByMtime(MediaAlbum $album, string $nasDirectory = ''): array
    {
        $nas = $this->nasManager->driver();
        $files = $nas->listFiles($nasDirectory);
        $added = 0;
        $skipped = 0;

        foreach ($files as $entry) {
            if ($entry['type'] !== 'file') {
                continue;
            }

            // Ignorer les miniatures générées (stockées dans thumbs/)
            if ($this->isThumbPath($entry['path'])) {
                $skipped++;

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
        $nas = $this->nasManager->driver();
        $files = $nas->listFiles($nasDirectory);
        $updated = 0;
        $unchanged = 0;

        foreach ($files as $entry) {
            if ($entry['type'] !== 'file') {
                continue;
            }

            // Ignorer les miniatures générées
            if ($this->isThumbPath($entry['path'])) {
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
                    'path' => $entry['path'],
                    'item_id' => $existing->id,
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
                'path' => $originalPath,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    // =========================================================================
    // EXIF
    // =========================================================================

    /**
     * Re-extrait les métadonnées EXIF de tous les MediaItems images du tenant courant.
     *
     * Pour chaque item :
     *   1. Télécharge le fichier depuis le NAS (readFile)
     *   2. Écrit dans un fichier temporaire (exif_read_data nécessite un chemin)
     *   3. Extrait les EXIF via extractExif()
     *   4. Met à jour exif_data, width_px, height_px si absents ou si --force
     *
     * @param  NasConnectorInterface  $nas  Driver NAS du tenant courant
     * @param  bool  $force  true = ré-extraire même si exif_data déjà rempli
     * @param  callable|null  $output  Callback de progression (string $msg)
     * @return array{updated: int, skipped: int, errors: int}
     */
    public function refreshExif(
        NasConnectorInterface $nas,
        bool $force = false,
        ?callable $output = null,
    ): array {
        $updated = 0;
        $skipped = 0;
        $errors = 0;

        $log = $output ?? fn (string $msg) => Log::info($msg);

        // Traiter uniquement les images JPEG/TIFF (seuls formats avec EXIF natif)
        $query = MediaItem::whereIn('mime_type', ['image/jpeg', 'image/tiff'])
            ->orderBy('id');

        if (! $force) {
            $query->whereNull('exif_data');
        }

        // Traitement par chunks pour éviter les problèmes mémoire sur grands volumes
        $query->chunk(50, function ($items) use ($nas, $force, $log, &$updated, &$skipped, &$errors) {
            foreach ($items as $item) {
                try {
                    // Lire le fichier depuis le NAS
                    $contents = $nas->readFile($item->file_path);

                    if (empty($contents)) {
                        $log("⚠ Fichier vide ou illisible : {$item->file_path}");
                        $errors++;

                        continue;
                    }

                    // Écriture dans un fichier temporaire (exif_read_data() attend un chemin)
                    $tmpPath = tempnam(sys_get_temp_dir(), 'pladigit_exif_');
                    file_put_contents($tmpPath, $contents);

                    try {
                        $exifData = $this->extractExif($tmpPath, $item->mime_type);
                        [$width, $height] = $this->getImageDimensions($tmpPath, $item->mime_type);
                    } finally {
                        @unlink($tmpPath);
                    }

                    $changes = [];

                    if (! empty($exifData) && ($force || empty($item->exif_data))) {
                        $changes['exif_data'] = $exifData;
                    }

                    if ($width && ($force || ! $item->width_px)) {
                        $changes['width_px'] = $width;
                    }

                    if ($height && ($force || ! $item->height_px)) {
                        $changes['height_px'] = $height;
                    }

                    if (! empty($changes)) {
                        $item->update($changes);
                        $log("✓ #{$item->id} {$item->file_name}");
                        $updated++;
                    } else {
                        $skipped++;
                    }

                } catch (\Throwable $e) {
                    $log("✗ #{$item->id} {$item->file_name} — {$e->getMessage()}");
                    Log::error('MediaService::refreshExif — erreur item', [
                        'item_id' => $item->id,
                        'file_path' => $item->file_path,
                        'error' => $e->getMessage(),
                    ]);
                    $errors++;
                }
            }
        });

        return compact('updated', 'skipped', 'errors');
    }

    /**
     * Extrait les métadonnées EXIF d'un fichier image (JPEG/TIFF uniquement).
     *
     * Notes :
     *  - Seuls JPEG et TIFF contiennent des données EXIF natives.
     *  - exif_read_data() avec sections_used=true retourne un tableau par section
     *    (IFD0, EXIF, GPS…) ce qui permet de récupérer le bloc GPS correctement.
     *  - Les fractions scalaires ("1/100") sont converties par sanitizeExif().
     *
     * @return array<string, mixed>
     */
    public function extractExif(string $filePath, string $mimeType): array
    {
        // Normaliser image/tif → image/tiff
        $mimeType = ($mimeType === 'image/tif') ? 'image/tiff' : $mimeType;

        if (! in_array($mimeType, ['image/jpeg', 'image/tiff'])) {
            return [];
        }

        if (! function_exists('exif_read_data')) {
            return [];
        }

        try {
            // sections_used=true → tableau par section (IFD0, EXIF, GPS, COMPUTED…)
            // Indispensable pour accéder aux données GPS qui sont dans leur propre section.
            $sections = @exif_read_data($filePath, 'ANY_TAG', true);

            if (! is_array($sections)) {
                return [];
            }

            // Fusionner toutes les sections en un tableau plat,
            // en donnant la priorité à la section GPS pour ses clés propres.
            $flat = [];
            foreach ($sections as $sectionName => $sectionData) {
                if (! is_array($sectionData)) {
                    continue;
                }
                // La section GPS contient ses clés natives (GPSLatitude, GPSLongitude…)
                // Les autres sections peuvent contenir des clés homonymes moins précises.
                foreach ($sectionData as $key => $value) {
                    if (! isset($flat[$key]) || $sectionName === 'GPS') {
                        $flat[$key] = $value;
                    }
                }
            }

            return $this->sanitizeExif($flat);
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
        $safe = Str::slug(pathinfo($originalName, PATHINFO_FILENAME));
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $unique = Str::random(8);

        // Album libre (nas_path défini) → on écrit directement dans son dossier NAS.
        // Album protégé (nas_path null) → zone interne albums/{id}/{année}/{mois}/.
        if ($album->nas_path) {
            return rtrim($album->nas_path, '/')."/{$safe}-{$unique}.{$ext}";
        }

        $date = now()->format('Y/m');

        return "albums/{$album->id}/{$date}/{$safe}-{$unique}.{$ext}";
    }

    /**
     * Construit le chemin de la miniature depuis le chemin original.
     * Ex : albums/42/2026/04/thumbs/mon-fichier-XXXX.jpg
     */
    private function buildThumbPath(string $originalPath): string
    {
        $dir = dirname($originalPath);
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
        $ext = strtolower(pathinfo($entry['name'], PATHINFO_EXTENSION));
        $mimeType = self::ALLOWED_EXTENSIONS[$ext] ?? 'application/octet-stream';

        // Lecture du contenu pour miniature + EXIF + SHA-256
        $contents = null;
        $thumbPath = null;
        $exifData = null;
        $width = null;
        $height = null;
        $sha256 = null;

        try {
            $contents = $nas->readFile($entry['path']);
            $sha256 = hash('sha256', $contents);

            // Miniature (images uniquement)
            if (str_starts_with($mimeType, 'image/')) {
                $thumbPath = $this->generateThumbnail($contents, $entry['path'], $nas);

                // EXIF via fichier temporaire
                $tmpPath = tempnam(sys_get_temp_dir(), 'pladigit_nas_');
                if ($tmpPath !== false) {
                    file_put_contents($tmpPath, $contents);
                    $exifData = $this->extractExif($tmpPath, $mimeType) ?: null;
                    [$width, $height] = $this->getImageDimensions($tmpPath, $mimeType);
                    @unlink($tmpPath);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('MediaService::ingestNasFile — lecture fichier échouée, ingestion sans métadonnées', [
                'path' => $entry['path'],
                'error' => $e->getMessage(),
            ]);
        }

        MediaItem::create([
            'album_id' => $album->id,
            'uploaded_by' => $album->created_by,
            'file_name' => $entry['name'],
            'file_path' => $entry['path'],
            'thumb_path' => $thumbPath,
            'mime_type' => $mimeType,
            'file_size_bytes' => $entry['size'],
            'width_px' => $width,
            'height_px' => $height,
            'exif_data' => $exifData,
            'caption' => null,
            'sha256_hash' => $sha256,
        ]);
    }

    /**
     * Vérifie que l'ajout du fichier ne dépasse pas le quota de l'organisation.
     *
     * Le quota (storage_quota_mb) est défini sur l'Organization dans pladigit_platform.
     * La valeur par défaut est 10 240 Mo (10 Go), conformément à la migration.
     *
     * @throws RuntimeException si le quota serait dépassé après l'upload
     */
    private function assertQuota(UploadedFile $file, User $uploader): void
    {
        $org = app(\App\Services\TenantManager::class)->current();

        if (! $org) {
            return; // Pas de tenant résolu (tests unitaires, CLI) → on laisse passer
        }

        $quotaMb = $org->storage_quota_mb ?? 10240;
        $quotaBytes = $quotaMb * 1024 * 1024;
        $usedBytes = (int) MediaItem::on('tenant')->whereNull('deleted_at')->sum('file_size_bytes');
        $incomingBytes = $file->getSize();

        if (($usedBytes + $incomingBytes) > $quotaBytes) {
            $usedMb = round($usedBytes / 1048576, 1);
            $freeMb = max(0, round(($quotaBytes - $usedBytes) / 1048576, 1));
            $fileMb = round($incomingBytes / 1048576, 1);

            throw new RuntimeException(
                'Quota de stockage dépassé. '
                ."Utilisé : {$usedMb} Mo / {$quotaMb} Mo. "
                ."Espace libre : {$freeMb} Mo. "
                ."Fichier : {$fileMb} Mo."
            );
        }
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
     * Retourne true si le chemin appartient au dossier thumbs/ généré automatiquement.
     * Ces fichiers ne doivent jamais être ingérés comme des médias.
     * Ex : albums/1/2026/03/thumbs/photo_thumb.jpg → true
     */
    private function isThumbPath(string $path): bool
    {
        // Normaliser les séparateurs et vérifier la présence de /thumbs/
        $normalized = str_replace('\\', '/', $path);

        return str_contains($normalized, '/thumbs/');
    }

    /**
     * Retourne les dimensions [width, height] d'une image, ou [null, null].
     *
     * @return array{int|null, int|null}
     */
    public function getImageDimensions(string $path, string $mimeType): array
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
     * Gère deux cas de fractions EXIF :
     *  - Scalaire string "1/100"  → ExposureTime, FNumber, FocalLength
     *  - Tableau de strings ["48/1", "0/1", "0/1"] → GPSLatitude, GPSLongitude
     *
     * ISOSpeedRatings est parfois un entier, parfois un tableau d'entiers
     * selon l'APN : on normalise toujours en scalaire.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function sanitizeExif(array $raw): array
    {
        $keep = [
            // Dates
            'DateTime', 'DateTimeOriginal', 'DateTimeDigitized',
            // Appareil
            'Make', 'Model', 'Software',
            // Exposition
            'ExposureTime', 'FNumber', 'ISOSpeedRatings',
            'ExposureMode', 'ExposureProgram', 'ExposureBiasValue',
            'ShutterSpeedValue', 'ApertureValue', 'MaxApertureValue',
            'BrightnessValue',
            // Optique
            'FocalLength', 'FocalLengthIn35mmFilm',
            // Divers prise de vue
            'Flash', 'MeteringMode', 'WhiteBalance',
            'SceneCaptureType', 'LightSource', 'Orientation',
            // GPS
            'GPSLatitude', 'GPSLatitudeRef',
            'GPSLongitude', 'GPSLongitudeRef',
            'GPSAltitude', 'GPSAltitudeRef',
            // Dimensions
            'ImageWidth', 'ImageLength',
        ];

        $result = [];

        foreach ($keep as $key) {
            if (! isset($raw[$key])) {
                continue;
            }

            $value = $raw[$key];

            // ── Cas 1 : fraction scalaire string "num/den" ──────────────────
            // ExposureTime ("1/100"), FNumber ("28/10"), FocalLength ("500/10")
            if (is_string($value) && str_contains($value, '/')) {
                [$num, $den] = explode('/', $value, 2);
                $value = ($den != 0) ? (float) $num / (float) $den : 0.0;
            }

            // ── Cas 2 : tableau (GPS ou multi-valeur) ────────────────────────
            // Chaque élément peut être une fraction string ou un scalaire
            elseif (is_array($value)) {
                $value = array_map(function ($v) {
                    if (is_string($v) && str_contains($v, '/')) {
                        [$num, $den] = explode('/', $v, 2);

                        return $den != 0 ? (float) $num / (float) $den : 0.0;
                    }

                    return is_scalar($v) ? $v : null;
                }, $value);
                $value = array_values(array_filter($value, fn ($v) => $v !== null));
            }

            // ── Cas 3 : ISOSpeedRatings — normaliser en scalaire ─────────────
            // Certains appareils retournent un tableau [800], d'autres l'entier 800
            if ($key === 'ISOSpeedRatings' && is_array($value)) {
                $value = $value[0] ?? null;
            }

            if (is_scalar($value) || is_array($value)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
