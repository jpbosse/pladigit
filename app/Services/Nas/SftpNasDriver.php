<?php

namespace App\Services\Nas;

use RuntimeException;

/**
 * Driver NAS SFTP — connexion à un NAS Linux/Synology/QNAP via SSH.
 * Dépendance native : extension PHP ssh2 (php-ssh2).
 *
 * Installation : sudo apt install php8.4-ssh2
 */
class SftpNasDriver implements NasConnectorInterface
{
    private mixed $connection = null;

    private mixed $sftp = null;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $username,
        private readonly string $password,
        private readonly string $rootPath = '/',
    ) {}

    // ─────────────────────────────────────────────────────────────
    //  Connexion
    // ─────────────────────────────────────────────────────────────

    public function testConnection(): bool
    {
        try {
            $this->connect();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  Opérations fichiers
    // ─────────────────────────────────────────────────────────────

    public function listFiles(string $directory): array
    {
        $sftp = $this->getSftp();
        $fullPath = $this->resolve($directory);
        $handle = opendir("ssh2.sftp://{$sftp}/{$fullPath}");

        if ($handle === false) {
            return [];
        }

        $entries = [];
        while (($file = readdir($handle)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $filePath = ltrim($directory.'/'.$file, '/');
            $fullFilePath = $this->resolve($filePath);
            $stat = @ssh2_sftp_stat($sftp, $fullFilePath);

            $entries[] = [
                'name' => $file,
                'path' => $filePath,
                'size' => (int) ($stat['size'] ?? 0),
                'mtime' => (int) ($stat['mtime'] ?? 0),
                'type' => isset($stat['mode']) && ($stat['mode'] & 0040000) ? 'dir' : 'file',
            ];
        }
        closedir($handle);

        return $entries;
    }

    public function readFile(string $path): string
    {
        $sftp = $this->getSftp();
        $fullPath = $this->resolve($path);
        $contents = file_get_contents("ssh2.sftp://{$sftp}/{$fullPath}");

        if ($contents === false) {
            throw new RuntimeException("SFTP : impossible de lire {$path}");
        }

        return $contents;
    }

    public function writeFile(string $path, string $contents): bool
    {
        $sftp = $this->getSftp();
        $fullPath = $this->resolve($path);
        $dir = dirname($fullPath);

        // Créer les dossiers intermédiaires
        $this->mkdirRecursive($sftp, $dir);

        return file_put_contents("ssh2.sftp://{$sftp}/{$fullPath}", $contents) !== false;
    }

    public function exists(string $path): bool
    {
        $sftp = $this->getSftp();
        $fullPath = $this->resolve($path);

        return @ssh2_sftp_stat($sftp, $fullPath) !== false;
    }

    public function sha256(string $path): string
    {
        // Lit par chunks pour éviter de charger tout en mémoire
        $sftp = $this->getSftp();
        $fullPath = $this->resolve($path);
        $handle = fopen("ssh2.sftp://{$sftp}/{$fullPath}", 'rb');

        if ($handle === false) {
            throw new RuntimeException("SFTP : impossible d'ouvrir {$path}");
        }

        $context = hash_init('sha256');
        while (! feof($handle)) {
            hash_update($context, fread($handle, 8192));
        }
        fclose($handle);

        return hash_final($context);
    }

    /**
     * Ouvre un flux SFTP pour le streaming par chunks (Range HTTP).
     *
     * @return array{mixed, mixed}
     */
    public function openReadStream(string $path): array
    {
        $sftp = $this->getSftp();
        $fullPath = $this->resolve($path);
        $handle = fopen("ssh2.sftp://{$sftp}/{$fullPath}", 'rb');

        if ($handle === false) {
            throw new RuntimeException("SFTP : impossible d'ouvrir le flux pour {$path}");
        }

        return [$sftp, $handle];
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

    public function mtime(string $path): int
    {
        $sftp = $this->getSftp();
        $stat = @ssh2_sftp_stat($sftp, $this->resolve($path));

        return (int) ($stat['mtime'] ?? 0);
    }

    public function size(string $path): int
    {
        $sftp = $this->getSftp();
        $stat = @ssh2_sftp_stat($sftp, $this->resolve($path));

        return (int) ($stat['size'] ?? 0);
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

    // ─────────────────────────────────────────────────────────────
    //  Helpers privés
    // ─────────────────────────────────────────────────────────────

    private function connect(): void
    {
        if ($this->connection !== null) {
            return;
        }

        if (! function_exists('ssh2_connect')) {
            throw new RuntimeException(
                'Extension PHP ssh2 non installée. Lancez : sudo apt install php8.4-ssh2'
            );
        }

        $conn = @ssh2_connect($this->host, $this->port);
        if ($conn === false) {
            throw new RuntimeException("SFTP : impossible de se connecter à {$this->host}:{$this->port}");
        }

        if (! @ssh2_auth_password($conn, $this->username, $this->password)) {
            throw new RuntimeException("SFTP : authentification échouée pour {$this->username}@{$this->host}");
        }

        $sftp = @ssh2_sftp($conn);
        if ($sftp === false) {
            throw new RuntimeException('SFTP : impossible d\'initialiser la session SFTP');
        }

        $this->connection = $conn;
        $this->sftp = $sftp;
    }

    private function getSftp(): mixed
    {
        $this->connect();

        return $this->sftp;
    }

    private function resolve(string $relativePath): string
    {
        $safe = str_replace(['..', '\\'], ['', '/'], $relativePath);
        $safe = ltrim($safe, '/');
        $root = rtrim($this->rootPath, '/');

        return $root.'/'.$safe;
    }

    private function mkdirRecursive(mixed $sftp, string $path): void
    {
        if (@ssh2_sftp_stat($sftp, $path) !== false) {
            return;
        }

        $parent = dirname($path);
        if ($parent !== $path) {
            $this->mkdirRecursive($sftp, $parent);
        }

        @ssh2_sftp_mkdir($sftp, $path, 0755);
    }
}
