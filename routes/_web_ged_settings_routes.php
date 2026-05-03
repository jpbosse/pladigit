<?php

use App\Http\Controllers\Admin\SettingsController;

/*
|--------------------------------------------------------------------------
| Routes GED settings à ajouter dans routes/web.php
|--------------------------------------------------------------------------
|
| Coller ces 3 lignes dans le groupe admin, juste après les routes NAS :
|   Route::put('settings/nas', ...)   ← ligne 156
|   Route::post('settings/nas/sync', ...) ← ligne 157
|
|   // Ajouter ici :
*/

Route::get('settings/ged', [SettingsController::class, 'ged'])->name('settings.ged');
Route::put('settings/ged', [SettingsController::class, 'updateGed'])->name('settings.ged.update');
Route::get('settings/ged/test', [SettingsController::class, 'testGed'])->name('settings.ged.test');
