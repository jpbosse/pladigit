<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Levée lors de la détection d'un doublon SHA-256 à l'upload.
 * Porte les informations nécessaires à la modale de confirmation.
 */
class DuplicateMediaException extends RuntimeException
{
    public function __construct(
        public readonly string $originalFileName,
        public readonly string $originalAlbumName,
        public readonly bool $sameAlbum,
        public readonly string $sha256,
    ) {
        $location = $sameAlbum
            ? 'dans cet album'
            : "dans l'album « {$originalAlbumName} »";

        parent::__construct(
            "Ce fichier est un doublon de « {$originalFileName} » {$location} (SHA-256 identique)."
        );
    }
}
