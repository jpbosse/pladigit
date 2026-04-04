<?php

namespace App\Services\Ged;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Driver de stockage GED local.
 *
 * Si un chemin racine est fourni (nas_ged_local_path), un disk dynamique
 * est créé sur ce chemin. Sinon, le disk « local » par défaut est utilisé
 * (storage/app/).
 *
 * Les chemins passés aux méthodes sont toujours relatifs à cette racine.
 * Exemple : « mairie/rh/{uuid}.pdf »
 */
class LocalGedDriver implements GedStorageInterface
{
    private Filesystem $disk;

    public function __construct(string $root = '')
    {
        $this->disk = $root !== ''
            ? Storage::build(['driver' => 'local', 'root' => $root])
            : Storage::disk('local');
    }

    public function put(string $path, mixed $contents): bool
    {
        return $this->disk->put($path, $contents);
    }

    public function get(string $path): string|false
    {
        if (! $this->exists($path)) {
            return false;
        }

        $content = $this->disk->get($path);

        return $content ?? false;
    }

    /**
     * @return resource|false
     */
    public function readStream(string $path): mixed
    {
        if (! $this->exists($path)) {
            return false;
        }

        return $this->disk->readStream($path);
    }

    public function delete(string $path): bool
    {
        if (! $this->exists($path)) {
            return true;
        }

        return $this->disk->delete($path);
    }

    public function exists(string $path): bool
    {
        return $this->disk->exists($path);
    }

    public function size(string $path): int
    {
        if (! $this->exists($path)) {
            return 0;
        }

        return $this->disk->size($path) ?? 0;
    }

    public function mimeType(string $path): string|false
    {
        if (! $this->exists($path)) {
            return false;
        }

        return $this->disk->mimeType($path);
    }

    public function mkdir(string $path): bool
    {
        return $this->disk->makeDirectory($path);
    }

    public function listDirectory(string $path): array
    {
        $entries = [];

        foreach ($this->disk->directories($path) as $dir) {
            $entries[] = [
                'name' => basename($dir),
                'path' => $dir,
                'type' => 'dir',
                'size' => 0,
                'mtime' => 0,
            ];
        }

        foreach ($this->disk->files($path) as $file) {
            $entries[] = [
                'name' => basename($file),
                'path' => $file,
                'type' => 'file',
                'size' => $this->disk->size($file) ?? 0,
                'mtime' => $this->disk->lastModified($file) ?? 0,
            ];
        }

        return $entries;
    }
}
