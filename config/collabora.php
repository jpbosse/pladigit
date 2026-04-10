<?php

return [

    /*
    |--------------------------------------------------------------------------
    | URL de l'instance Collabora Online (CODE)
    |--------------------------------------------------------------------------
    | Ex : https://collabora.mairie.fr
    | Doit être accessible depuis le navigateur ET depuis le serveur Laravel
    | (Collabora appelle les endpoints WOPI via HTTP).
    */
    'url' => env('COLLABORA_URL', ''),

    /*
    |--------------------------------------------------------------------------
    | URL fixe des endpoints WOPI (base)
    |--------------------------------------------------------------------------
    | URL de base utilisée pour construire WOPISrc — doit être accessible
    | depuis le serveur Collabora. Avec une URL fixe (ex : https://pladigit.fr),
    | Collabora n'a besoin que d'un seul aliasgroup, quel que soit le tenant.
    |
    | Si non défini, fallback sur APP_URL.
    */
    'wopi_url' => env('WOPI_URL', env('APP_URL', '')),

    /*
    |--------------------------------------------------------------------------
    | Chemin de l'éditeur dans l'instance Collabora
    |--------------------------------------------------------------------------
    | Collabora CODE 23+ : /browser/dist/cool.html
    | Versions plus anciennes : /loleaflet/dist/loleaflet.html
    */
    'editor_path' => env('COLLABORA_EDITOR_PATH', '/browser/dist/cool.html'),

    /*
    |--------------------------------------------------------------------------
    | TTL des tokens WOPI (en secondes)
    |--------------------------------------------------------------------------
    */
    'token_ttl' => (int) env('COLLABORA_TOKEN_TTL', 14400), // 4 heures

    /*
    |--------------------------------------------------------------------------
    | TTL des verrous WOPI (en minutes)
    |--------------------------------------------------------------------------
    | Durée de vie d'un verrou LOCK avant expiration automatique.
    | La spec WOPI recommande 30 minutes ; Collabora envoie REFRESH_LOCK
    | régulièrement pour maintenir le verrou actif tant que l'éditeur est ouvert.
    */
    'lock_ttl' => (int) env('COLLABORA_LOCK_TTL', 30), // minutes

    /*
    |--------------------------------------------------------------------------
    | Types MIME supportés par Collabora pour l'édition / visualisation
    |--------------------------------------------------------------------------
    | Utilisé pour afficher le bouton "Ouvrir dans Collabora".
    */
    'supported_mimes' => [
        // ODF
        'application/vnd.oasis.opendocument.text',
        'application/vnd.oasis.opendocument.spreadsheet',
        'application/vnd.oasis.opendocument.presentation',
        'application/vnd.oasis.opendocument.graphics',

        // Microsoft Office (Collabora les ouvre via conversion)
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    ],

];
