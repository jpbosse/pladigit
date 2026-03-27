<?php

namespace App\Services\Nas;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Driver NAS local — pour le développement et les tests.
 * Utilise storage/app/nas_simulation/ comme répertoire racine.
 * En production, remplacer par SmbDriver ou SftpDriver.
 */
class LocalNasDriver implements NasConnectorInterface
{
    /**
     * Chemin absolu vers la racine du NAS simulé.
     */
    private string $basePath;

    public function __construct(?string $basePath = null)
    {
        $this->basePath = $basePath ?? storage_path('app/nas_simulation');
    }

    /**
     * Teste la connexion : vérifie que le dossier racine existe et est lisible.
     */
    public function testConnection(): bool
    {
        if (! is_dir($this->basePath)) {
            mkdir($this->basePath, 0755, true);
        }

        return is_readable($this->basePath);
    }

    /**
     * Liste les fichiers d'un répertoire (non récursif).
     *
     * @return array<int, array{name: string, path: string, size: int, mtime: int, type: string}>
     */
    public function listFiles(string $directory): array
    {
        $fullPath = $this->resolve($directory);
        if (! is_dir($fullPath)) {
            return [];
        }
        $entries = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($fullPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($iterator as $item) {
            if (! $item->isFile()) {

                // Exclure les dossiers thumbs
                if (str_contains($item->getRealPath(), DIRECTORY_SEPARATOR.'thumbs'.DIRECTORY_SEPARATOR)) {
                    continue;
                }

                continue;
            }
            $absolutePath = $item->getRealPath();
            $relativePath = ltrim(str_replace($this->resolve(''), '', $absolutePath), '/');
            $entries[] = [
                'name' => $item->getFilename(),
                'path' => $relativePath,
                'size' => (int) $item->getSize(),
                'mtime' => (int) $item->getMTime(),
                'type' => 'file',
            ];
        }

        return $entries;
    }

    /**
     * Lit le contenu binaire d'un fichier.
     */
    public function readFile(string $path): string
    {
        $fullPath = $this->resolve($path);
        $this->assertFile($fullPath);

        $contents = file_get_contents($fullPath);

        if ($contents === false) {
            throw new RuntimeException("Impossible de lire le fichier : {$path}");
        }

        return $contents;
    }

    /**
     * Écrit un fichier (crée les répertoires intermédiaires si besoin).
     */
    public function writeFile(string $path, string $contents): bool
    {
        $fullPath = $this->resolve($path);
        $dir = dirname($fullPath);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return file_put_contents($fullPath, $contents) !== false;
    }

    /**
     * Vérifie l'existence d'un fichier ou dossier.
     */
    public function exists(string $path): bool
    {
        return file_exists($this->resolve($path));
    }

    /**
     * Supprime un fichier du NAS.
     */
    public function deleteFile(string $path): bool
    {
        $fullPath = $this->resolve($path);
        if (! is_file($fullPath)) {
            return true; // Déjà absent — pas d'erreur
        }

        return @unlink($fullPath);
    }

    public function mkdir(string $path): bool
    {
        $fullPath = $this->resolve($path);
        if (is_dir($fullPath)) {
            return true;
        }

        return mkdir($fullPath, 0775, true);
    }

    /**
     * Ouvre un flux de lecture pour le streaming par chunks (Range HTTP).
     *
     * @return array{mixed, mixed}
     */
    public function openReadStream(string $path): array
    {
        $fullPath = $this->resolve($path);
        $this->assertFile($fullPath);
        $handle = fopen($fullPath, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Impossible d'ouvrir le flux : {$path}");
        }

        return [null, $handle];
    }

    /**
     * @param  array{mixed, mixed}  $stream
     */
    public function closeReadStream(array $stream): void
    {
        [, $handle] = $stream;
        if (is_resource($handle)) {
            fclose($handle);
        }
    }

    /**
     * @param  array{mixed, mixed}  $stream
     */
    public function readChunk(array $stream, int $offset, int $length): string|false
    {
        [, $handle] = $stream;
        fseek($handle, $offset);
        $data = fread($handle, $length);

        return $data === '' ? false : $data;
    }

    /**
     * Calcule l'empreinte SHA-256 sans charger tout le fichier en mémoire.
     */
    public function sha256(string $path): string
    {
        $fullPath = $this->resolve($path);
        $this->assertFile($fullPath);

        $context = hash_init('sha256');
        $handle = fopen($fullPath, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Impossible d'ouvrir le fichier : {$path}");
        }

        while (! feof($handle)) {
            hash_update($context, fread($handle, 8192));
        }

        fclose($handle);

        return hash_final($context);
    }

    /**
     * Retourne la date de dernière modification (timestamp Unix).
     */
    public function mtime(string $path): int
    {
        $fullPath = $this->resolve($path);
        $this->assertFile($fullPath);

        return (int) filemtime($fullPath);
    }

    /**
     * Retourne la taille en octets.
     */
    public function size(string $path): int
    {
        $fullPath = $this->resolve($path);
        $this->assertFile($fullPath);

        return (int) filesize($fullPath);
    }

    public function moveDir(string $from, string $to): bool
    {
        $srcPath = $this->resolve($from);
        $dstPath = $this->resolve($to);

        if (! is_dir($srcPath)) {
            throw new RuntimeException("Dossier source introuvable : {$from}");
        }

        $dstParent = dirname($dstPath);
        if (! is_dir($dstParent)) {
            mkdir($dstParent, 0755, true);
        }

        return rename($srcPath, $dstPath);
    }

    public function moveFile(string $from, string $to): bool
    {
        $srcPath = $this->resolve($from);
        $dstPath = $this->resolve($to);

        if (! is_file($srcPath)) {
            throw new RuntimeException("Fichier source introuvable : {$from}");
        }

        $dstDir = dirname($dstPath);
        if (! is_dir($dstDir)) {
            mkdir($dstDir, 0755, true);
        }

        return rename($srcPath, $dstPath);
    }

    public function listDirectories(string $directory): array
    {
        $fullPath = $this->resolve($directory);
        if (! is_dir($fullPath)) {
            return [];
        }
        $entries = [];
        foreach (new \DirectoryIterator($fullPath) as $item) {
            if ($item->isDot() || ! $item->isDir()) {
                continue;
            }
            $absolutePath = $item->getRealPath();
            $relativePath = ltrim(str_replace($this->resolve(''), '', $absolutePath), '/');
            $entries[] = [
                'name' => $item->getFilename(),
                'path' => $relativePath,
                'size' => 0,
                'mtime' => (int) $item->getMTime(),
                'type' => 'directory',
            ];
        }

        return $entries;
    }

    // -------------------------------------------------------------------------
    // Helpers privés
    // -------------------------------------------------------------------------

    /**
     * Résout un chemin relatif vers un chemin absolu sécurisé.
     * Empêche toute traversée de répertoire (path traversal).
     */
    private function resolve(string $relativePath): string
    {
        // Bloquer toute tentative de traversée de répertoire
        if (str_contains($relativePath, '..')) {
            throw new \RuntimeException("Chemin interdit (path traversal détecté) : {$relativePath}");
        }

        $safe = str_replace('\\', '/', $relativePath);

        // Si le chemin est déjà absolu, on le retourne directement
        // (évite la duplication quand nas_local_path est un chemin absolu)
        if (str_starts_with($safe, '/')) {
            return rtrim($safe, '/');
        }

        $safe = ltrim($safe, '/');

        return $this->basePath.DIRECTORY_SEPARATOR.$safe;
    }

    /**
     * Lève une exception si le chemin n'est pas un fichier lisible.
     */
    private function assertFile(string $fullPath): void
    {
        if (! is_file($fullPath)) {
            throw new RuntimeException("Fichier introuvable : {$fullPath}");
        }
    }
}
