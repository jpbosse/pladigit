<?php

namespace App\Services\Nas;

/**
 * Contrat commun pour tous les drivers NAS.
 * Drivers disponibles : local, smb, sftp.
 */
interface NasConnectorInterface
{
    public function testConnection(): bool;

    /**
     * @return array<int, array{name: string, path: string, size: int, mtime: int, type: string}>
     */
    public function listFiles(string $directory): array;

    public function readFile(string $path): string;

    public function writeFile(string $path, string $contents): bool;

    public function exists(string $path): bool;

    public function deleteFile(string $path): bool;

    public function mkdir(string $path): bool;

    public function sha256(string $path): string;

    /** @return array{mixed, mixed} */
    public function openReadStream(string $path): array;

    /** @param array{mixed, mixed} $stream */
    public function closeReadStream(array $stream): void;

    /** @param array{mixed, mixed} $stream */
    public function readChunk(array $stream, int $offset, int $length): string|false;

    public function mtime(string $path): int;

    public function size(string $path): int;

    /**
     * Déplace (renomme) un fichier sur le NAS.
     * Crée les dossiers intermédiaires de destination si nécessaire.
     */
    public function moveFile(string $from, string $to): bool;

    /**
     * Déplace (renomme) un dossier entier sur le NAS.
     * Crée le dossier parent de destination si nécessaire.
     */
    public function moveDir(string $from, string $to): bool;

    /**
     * Liste les sous-dossiers directs d'un répertoire (non récursif).
     *
     * @return array<int, array{name: string, path: string, size: int, mtime: int, type: string}>
     */
    public function listDirectories(string $directory): array;
}
