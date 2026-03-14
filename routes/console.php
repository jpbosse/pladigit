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

// Re-extraction EXIF — chaque dimanche à 02h00
Schedule::command('media:refresh-exif')
    ->weeklyOn(0, '02:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->onFailure(function () {
        \Log::error('Re-extraction EXIF échouée');
    });
