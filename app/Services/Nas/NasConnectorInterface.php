<?php

namespace App\Services\Nas;

/**
 * Contrat commun pour tous les drivers NAS.
 * Drivers disponibles : local, smb, sftp.
 */
interface NasConnectorInterface
{
    /**
     * Teste la connexion au NAS.
     * Retourne true si la connexion est établie.
     */
    public function testConnection(): bool;

    /**
     * Liste les fichiers d'un répertoire.
     * Retourne un tableau de ['name', 'path', 'size', 'mtime', 'type'].
     *
     * @return array<int, array{name: string, path: string, size: int, mtime: int, type: string}>
     */
    public function listFiles(string $directory): array;

    /**
     * Lit le contenu binaire d'un fichier.
     */
    public function readFile(string $path): string;

    /**
     * Écrit un fichier sur le NAS (upload depuis navigateur).
     */
    public function writeFile(string $path, string $contents): bool;

    /**
     * Vérifie l'existence d'un fichier ou dossier.
     */
    public function exists(string $path): bool;

    /**
     * Calcule l'empreinte SHA-256 d'un fichier sans le charger entièrement en mémoire.
     */
    public function sha256(string $path): string;

    /**
     * Ouvre un flux de lecture pour le streaming par chunks (Range HTTP).
     * Retourne un tableau [context, handle] propre à chaque driver.
     * À fermer après usage via closeReadStream().
     *
     * @return array{mixed, mixed}
     */
    public function openReadStream(string $path): array;

    /**
     * Ferme un flux ouvert par openReadStream().
     *
     * @param  array{mixed, mixed}  $stream
     */
    public function closeReadStream(array $stream): void;

    /**
     * Lit N octets dans un flux ouvert, à partir d'un offset donné.
     * Retourne false en fin de flux.
     *
     * @param  array{mixed, mixed}  $stream
     */
    public function readChunk(array $stream, int $offset, int $length): string|false;

    /**
     * Retourne la date de dernière modification (timestamp Unix).
     */
    public function mtime(string $path): int;

    /**
     * Retourne la taille en octets.
     */
    public function size(string $path): int;
}
