<?php

namespace App\Services\Nas;

use RuntimeException;

/**
 * Driver NAS SMB/CIFS — connexion à un partage Windows ou NAS Samba.
 *
 * Dépendance : extension PHP smbclient (libsmbclient-php).
 * Installation : sudo apt install php8.2-smbclient
 *
 * Format du chemin UNC : //hote/partage
 * Exemple : //192.168.1.10/photos
 */
class SmbNasDriver implements NasConnectorInterface
{
    /** Ressource smbclient ouverte (lazy). */
    private mixed $state = null;

    public function __construct(
        private readonly string $host,
        private readonly string $share,
        private readonly string $username,
        private readonly string $password,
        private readonly string $workgroup = 'WORKGROUP',
        private readonly string $rootPath = '',
    ) {}

    // ─────────────────────────────────────────────────────────────
    //  Interface publique
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

    /**
     * Liste les fichiers d'un répertoire (récursif, exclut les thumbs).
     *
     * @return array<int, array{name: string, path: string, size: int, mtime: int, type: string}>
     */
    public function listFiles(string $directory): array
    {
        $smb = $this->getState();
        $dir = $this->resolve($directory);
        $list = @smbclient_ls($smb, $dir);

        if ($list === false) {
            return [];
        }

        $entries = [];

        foreach ($list as $name => $info) {
            if (in_array($name, ['.', '..'], strict: true)) {
                continue;
            }

            $relativePath = ltrim($directory.'/'.$name, '/');

            // Exclure les dossiers thumbs
            if (str_contains($relativePath, '/thumbs/')) {
                continue;
            }

            $type = ($info['attr'] & 0x10) ? 'dir' : 'file'; // 0x10 = ATTR_DIRECTORY

            if ($type === 'dir') {
                // Récursion dans les sous-dossiers
                $sub = $this->listFiles($relativePath);
                foreach ($sub as $subEntry) {
                    $entries[] = $subEntry;
                }

                continue;
            }

            $entries[] = [
                'name' => $name,
                'path' => $relativePath,
                'size' => (int) ($info['size'] ?? 0),
                'mtime' => (int) ($info['mtime'] ?? 0),
                'type' => 'file',
            ];
        }

        return $entries;
    }

    /**
     * Lit le contenu binaire d'un fichier en entier.
     * Pour les fichiers volumineux, préférer openReadStream().
     */
    public function readFile(string $path): string
    {
        $smb = $this->getState();
        $fullPath = $this->resolve($path);
        $handle = @smbclient_open($smb, $fullPath, 'r');

        if ($handle === false) {
            throw new RuntimeException("SMB : impossible d'ouvrir {$path}");
        }

        $contents = '';
        while (! smbclient_eof($smb, $handle)) {
            $chunk = smbclient_read($smb, $handle, 65536);
            if ($chunk === false) {
                break;
            }
            $contents .= $chunk;
        }

        smbclient_close($smb, $handle);

        return $contents;
    }

    /**
     * Ouvre un flux de lecture SMB pour le streaming par Range.
     * Retourne [resource_smb, handle_file] — à fermer après usage.
     *
     * @return array{mixed, mixed}
     */
    public function openReadStream(string $path): array
    {
        $smb = $this->getState();
        $fullPath = $this->resolve($path);
        $handle = @smbclient_open($smb, $fullPath, 'r');

        if ($handle === false) {
            throw new RuntimeException("SMB : impossible d'ouvrir le flux pour {$path}");
        }

        return [$smb, $handle];
    }


/**
     * @param array{mixed, mixed} $stream
     */
    public function closeReadStream(array $stream): void
    {
        [$smb, $handle] = $stream;
        if ($handle !== false && $handle !== null) {
            @smbclient_close($smb, $handle);
        }
    }

    /**
     * @param array{mixed, mixed} $stream
     */
    public function readChunk(array $stream, int $offset, int $length): string|false
    {
        [$smb, $handle] = $stream;
        if (@smbclient_eof($smb, $handle)) {
            return false;
        }
        $data = @smbclient_read($smb, $handle, $length);

        return $data === false ? false : $data;
    }





    /**
     * Écrit un fichier sur le partage SMB.
     */
    public function writeFile(string $path, string $contents): bool
    {
        $smb = $this->getState();
        $fullPath = $this->resolve($path);

        $this->mkdirRecursive(dirname($path));

        $handle = @smbclient_open($smb, $fullPath, 'w');

        if ($handle === false) {
            return false;
        }

        $result = smbclient_write($smb, $handle, $contents);
        smbclient_close($smb, $handle);

        return $result !== false;
    }

    public function exists(string $path): bool
    {
        $smb = $this->getState();
        $stat = @smbclient_stat($smb, $this->resolve($path));

        return $stat !== false;
    }

    public function sha256(string $path): string
    {
        $smb = $this->getState();
        $fullPath = $this->resolve($path);
        $handle = @smbclient_open($smb, $fullPath, 'r');

        if ($handle === false) {
            throw new RuntimeException("SMB : impossible d'ouvrir {$path} pour SHA-256");
        }

        $context = hash_init('sha256');

        while (! smbclient_eof($smb, $handle)) {
            $chunk = smbclient_read($smb, $handle, 8192);
            if ($chunk === false) {
                break;
            }
            hash_update($context, $chunk);
        }

        smbclient_close($smb, $handle);

        return hash_final($context);
    }

    public function mtime(string $path): int
    {
        $smb = $this->getState();
        $stat = @smbclient_stat($smb, $this->resolve($path));

        return (int) ($stat['mtime'] ?? 0);
    }

    public function size(string $path): int
    {
        $smb = $this->getState();
        $stat = @smbclient_stat($smb, $this->resolve($path));

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
            'name'  => $item->getFilename(),
            'path'  => $relativePath,
            'size'  => 0,
            'mtime' => (int) $item->getMTime(),
            'type'  => 'directory',
        ];
    }

    return $entries;
}


    // ─────────────────────────────────────────────────────────────
    //  Helpers privés
    // ─────────────────────────────────────────────────────────────

    private function connect(): void
    {
        if ($this->state !== null) {
            return;
        }

        if (! function_exists('smbclient_state_new')) {
            throw new RuntimeException(
                'Extension PHP smbclient non installée. Lancez : sudo apt install php8.2-smbclient'
            );
        }

        $state = smbclient_state_new();

        if ($state === false) {
            throw new RuntimeException('SMB : impossible de créer l\'état smbclient.');
        }

        smbclient_option_set($state, SMBCLIENT_OPT_TIMEOUT, 10);

        $result = @smbclient_state_init($state, $this->workgroup, $this->username, $this->password);

        if ($result === false) {
            throw new RuntimeException(
                "SMB : authentification échouée pour {$this->username}@{$this->host}/{$this->share}"
            );
        }

        $this->state = $state;
    }

    private function getState(): mixed
    {
        $this->connect();

        return $this->state;
    }

    /**
     * Résout un chemin relatif en chemin SMB complet.
     * Format : smb://host/share/root/path
     */
    private function resolve(string $relativePath): string
    {
        $safe = str_replace(['..', '\\'], ['', '/'], $relativePath);
        $safe = ltrim($safe, '/');
        $root = trim($this->rootPath, '/');

        $base = "smb://{$this->host}/{$this->share}";

        if ($root !== '') {
            $base .= "/{$root}";
        }

        return $safe !== '' ? "{$base}/{$safe}" : $base;
    }

    /**
     * Crée récursivement les dossiers sur le partage SMB.
     */
    private function mkdirRecursive(string $path): void
    {
        if ($path === '' || $path === '.') {
            return;
        }

        $smb = $this->getState();
        $fullPath = $this->resolve($path);

        if (@smbclient_stat($smb, $fullPath) !== false) {
            return; // Dossier déjà existant
        }

        $this->mkdirRecursive(dirname($path));
        @smbclient_mkdir($smb, $fullPath);
    }
}
