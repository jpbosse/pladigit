<?php
 
use Illuminate\Support\Facades\Route;
 
// Routes API /api/v1/ — vide en Phase 1
// Les endpoints API arriveront en Phase 4+
Route::prefix('v1')->middleware(['tenant', 'auth:sanctum'])->group(function () {
    // À compléter
});
