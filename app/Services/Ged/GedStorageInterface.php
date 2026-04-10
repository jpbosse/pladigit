<?php

namespace App\Services\Ged;

/**
 * Contrat commun pour tous les drivers de stockage GED.
 * Drivers disponibles : local (v1), NAS extensible (v2+).
 *
 * Le chemin $path est toujours relatif à la racine du driver GED.
 * Convention : ged/{org_slug}/{YYYY}/{MM}/{uuid}.{ext}
 */
interface GedStorageInterface
{
    /**
     * Stocke un contenu (string ou resource) à l'emplacement $path.
     */
    public function put(string $path, mixed $contents): bool;

    /**
     * Retourne le contenu du fichier, ou false si inexistant.
     */
    public function get(string $path): string|false;

    /**
     * Retourne un stream lisible pour un téléchargement efficace.
     *
     * @return resource|false
     */
    public function readStream(string $path): mixed;

    /**
     * Supprime le fichier. Retourne true si supprimé ou inexistant.
     */
    public function delete(string $path): bool;

    /**
     * Vérifie l'existence du fichier.
     */
    public function exists(string $path): bool;

    /**
     * Taille du fichier en octets. Retourne 0 si inconnu.
     */
    public function size(string $path): int;

    /**
     * Type MIME détecté, ou false si inconnu.
     */
    public function mimeType(string $path): string|false;

    /**
     * Crée le répertoire $path (et ses parents si nécessaire).
     * Retourne true si créé ou déjà existant.
     */
    public function mkdir(string $path): bool;

    /**
     * Liste le contenu d'un répertoire (fichiers + sous-dossiers).
     *
     * Chaque entrée :
     *   ['name' => string, 'path' => string, 'type' => 'file'|'dir', 'size' => int, 'mtime' => int]
     *
     * @return array<int, array{name: string, path: string, type: 'file'|'dir', size: int, mtime: int}>
     */
    public function listDirectory(string $path): array;
}
