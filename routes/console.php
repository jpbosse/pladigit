<?php

// routes/console.php

use Illuminate\Support\Facades\Schedule;

// Synchronisation LDAP — toutes les heures, sans chevauchement
Schedule::command('pladigit:sync-ldap')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground()
    ->onFailure(function () {
        \Log::error('Synchronisation LDAP échouée');
    });

// Synchronisation NAS légère (mtime) — toutes les heures
Schedule::command('nas:sync')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground()
    ->onFailure(function () {
        \Log::error('Synchronisation NAS (mtime) échouée');
    });

// Synchronisation NAS complète (SHA-256) — chaque nuit à 23h30
Schedule::command('nas:sync --deep')
    ->dailyAt('23:30')
    ->withoutOverlapping()
    ->runInBackground()
    ->onFailure(function () {
        \Log::error('Synchronisation NAS (SHA-256) échouée');
    });

// Correction des droits GED — 5 min avant le sync (fichiers copiés par un autre user OS)
Schedule::command('ged:fix-perms')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

// Synchronisation GED — toutes les heures
Schedule::command('ged:sync')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground()
    ->onFailure(function () {
        \Log::error('Synchronisation GED échouée');
    });

// Re-extraction EXIF — chaque dimanche à 02h00
Schedule::command('media:refresh-exif')
    ->weeklyOn(0, '02:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->onFailure(function () {
        \Log::error('Re-extraction EXIF échouée');
    });

// Génération des occurrences de tâches récurrentes — chaque jour à 06h00
Schedule::command('pladigit:generate-recurring-tasks')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->onFailure(function () {
        \Log::error('Génération tâches récurrentes échouée');
    });

// Purge GED — documents soft-deleted + versions excédentaires — chaque nuit à 02h30
Schedule::command('ged:purge')
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->runInBackground()
    ->onFailure(function () {
        \Log::error('Purge GED échouée');
    });

// Remise à zéro de la démo — chaque nuit à minuit
Schedule::command('demo:reset --slug=demo')
    ->dailyAt('00:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->environments(['production'])
    ->onFailure(function () {
        \Log::error('Remise à zéro démo échouée');
    });

// Purge des données expirées — chaque nuit à 03h00
// Couvre : liens de partage, invitations non utilisées, sessions DB obsolètes
Schedule::command('pladigit:purge-expired')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->onFailure(function () {
        \Log::error('Purge des données expirées échouée');
    });
