<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Driver NAS par défaut
    |--------------------------------------------------------------------------
    | Valeurs possibles : local, smb, sftp
    | Surchargeable par tenant via tenant_settings.nas_driver
    */
    'default_driver' => env('NAS_DRIVER', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Chemin du NAS simulé en local (driver=local)
    |--------------------------------------------------------------------------
    */
    'local_path' => env('NAS_LOCAL_PATH', storage_path('app/nas_simulation')),

    /*
    |--------------------------------------------------------------------------
    | Types de fichiers acceptés
    |--------------------------------------------------------------------------
    */
    'allowed_mimes' => [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'image/tiff',
        'video/mp4',
        'video/quicktime',
        'video/x-msvideo',
        'video/x-matroska',
        'application/pdf',
    ],

    /*
    |--------------------------------------------------------------------------
    | Taille maximale d'un fichier uploadé (en octets)
    |--------------------------------------------------------------------------
    */
    'max_file_size' => env('NAS_MAX_FILE_SIZE', 200 * 1024 * 1024), // 200 Mo

    /*
    |--------------------------------------------------------------------------
    | Paramètres de synchronisation périodique
    |--------------------------------------------------------------------------
    */
    'sync' => [
        'mtime_check_interval_minutes' => 60,   // Niveau 1 — léger
        'sha256_check_hour' => 23,    // Niveau 2 — quotidien à 23h30
        'sha256_check_minute' => 30,
    ],
];
