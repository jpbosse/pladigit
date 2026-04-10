<?php

namespace App\Services\Ged;

use App\Services\Nas\NasConnectorInterface;

/**
 * Adapte un driver NAS (Local / SFTP / SMB) vers GedStorageInterface.
 *
 * Permet d'utiliser exactement le même NAS que la photothèque
 * (ou un NAS distinct) pour stocker les documents GED,
 * sans dupliquer la logique de connexion.
 */
class GedNasDriver implements GedStorageInterface
{
    public function __construct(private readonly NasConnectorInterface $nas) {}

    public function put(string $path, mixed $contents): bool
    {
        if (is_resource($contents)) {
            $contents = stream_get_contents($contents);
        }

        return $this->nas->writeFile($path, (string) $contents);
    }

    public function get(string $path): string|false
    {
        if (! $this->nas->exists($path)) {
            return false;
        }

        try {
            return $this->nas->readFile($path);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return resource|false
     */
    public function readStream(string $path): mixed
    {
        if (! $this->nas->exists($path)) {
            return false;
        }

        try {
            [$stream] = $this->nas->openReadStream($path);

            return is_resource($stream) ? $stream : false;
        } catch (\Throwable) {
            return false;
        }
    }

    public function delete(string $path): bool
    {
        if (! $this->nas->exists($path)) {
            return true;
        }

        return $this->nas->deleteFile($path);
    }

    public function exists(string $path): bool
    {
        return $this->nas->exists($path);
    }

    public function size(string $path): int
    {
        // Récupère la taille via listFiles sur le répertoire parent
        $dir = dirname($path);
        $filename = basename($path);

        try {
            $entries = $this->nas->listFiles($dir);

            foreach ($entries as $entry) {
                if ($entry['name'] === $filename) {
                    return (int) $entry['size'];
                }
            }
        } catch (\Throwable) {
            // Silencieux — retourne 0 en cas d'erreur
        }

        return 0;
    }

    public function mkdir(string $path): bool
    {
        // La plupart des drivers NAS (SFTP/SMB) créent les répertoires
        // automatiquement à l'écriture via put(). Ici, on tente la création
        // explicite si le connecteur le supporte, sinon on retourne true.
        try {
            if (method_exists($this->nas, 'makeDirectory')) {
                return (bool) $this->nas->makeDirectory($path);
            }
        } catch (\Throwable) {
            // Non-bloquant
        }

        return true;
    }

    public function listDirectory(string $path): array
    {
        $entries = [];

        try {
            foreach ($this->nas->listDirectories($path) as $dir) {
                $entries[] = [
                    'name' => basename($dir['path']),
                    'path' => $dir['path'],
                    'type' => 'dir',
                    'size' => 0,
                    'mtime' => 0,
                ];
            }

            foreach ($this->nas->listFiles($path) as $file) {
                if ($file['type'] !== 'file') {
                    continue;
                }
                $entries[] = [
                    'name' => $file['name'],
                    'path' => $file['path'],
                    'type' => 'file',
                    'size' => (int) ($file['size'] ?? 0),
                    'mtime' => (int) ($file['mtime'] ?? 0),
                ];
            }
        } catch (\Throwable) {
            // Répertoire inexistant ou inaccessible — retourne tableau vide
        }

        return $entries;
    }

    public function mimeType(string $path): string|false
    {
        // Détection par extension (le NAS ne fournit pas le MIME)
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $map = [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'odt' => 'application/vnd.oasis.opendocument.text',
            'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
            'odp' => 'application/vnd.oasis.opendocument.presentation',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
            'zip' => 'application/zip',
        ];

        return $map[$ext] ?? false;
    }
}
