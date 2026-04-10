<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Types MIME acceptés pour l'upload GED
    |--------------------------------------------------------------------------
    | Plus large que la photothèque (images seulement) : documents bureautiques,
    | PDF, texte, archives légères, images.
    */
    'allowed_mimes' => [
        // PDF
        'application/pdf',

        // Microsoft Office
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',

        // LibreOffice / ODF
        'application/vnd.oasis.opendocument.text',
        'application/vnd.oasis.opendocument.spreadsheet',
        'application/vnd.oasis.opendocument.presentation',
        'application/vnd.oasis.opendocument.graphics',

        // Images
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/tiff',
        'image/svg+xml',

        // Texte
        'text/plain',
        'text/csv',
        'text/html',

        // Archives
        'application/zip',
        'application/x-zip-compressed',
    ],

    /*
    |--------------------------------------------------------------------------
    | Taille maximale d'un fichier uploadé (en octets)
    |--------------------------------------------------------------------------
    | Configurable via GED_MAX_FILE_SIZE dans .env.
    | Valeur par défaut : 50 Mo.
    */
    'max_file_size' => env('GED_MAX_FILE_SIZE', 50 * 1024 * 1024),

    /*
    |--------------------------------------------------------------------------
    | Répertoire de stockage sur le disk « local »
    |--------------------------------------------------------------------------
    | Les fichiers sont stockés dans :
    |   storage/app/private/ged/{org_slug}/{YYYY}/{MM}/{uuid}.{ext}
    */
    'storage_prefix' => 'ged',

];
