<?php
 
use Illuminate\Support\Facades\Schedule;
 
// Synchronisation LDAP — toutes les heures, sans chevauchement
Schedule::command('pladigit:sync-ldap')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground()
    ->onFailure(function () {
        \Log::error('Synchronisation LDAP échouée');
    });
