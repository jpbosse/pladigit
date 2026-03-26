<?php

use App\Http\Controllers\Ged\GedFolderController;

/*
|--------------------------------------------------------------------------
| GED — Gestion Électronique de Documents (Phase 6)
|--------------------------------------------------------------------------
| Toutes ces routes sont incluses dans web.php sous le groupe
| middleware(['auth', 'force-pwd-change']) + middleware('module:ged').
*/

Route::prefix('ged')->name('ged.')->middleware('module:ged')->group(function () {

    Route::get('/', [GedFolderController::class, 'index'])->name('index');

    Route::get('folders/{folder}', [GedFolderController::class, 'show'])->name('folders.show');
    Route::get('folders/{folder}/children', [GedFolderController::class, 'children'])->name('folders.children');

    Route::post('folders', [GedFolderController::class, 'store'])->name('folders.store');
    Route::put('folders/{folder}', [GedFolderController::class, 'update'])->name('folders.update');
    Route::post('folders/{folder}/move', [GedFolderController::class, 'move'])->name('folders.move');
    Route::delete('folders/{folder}', [GedFolderController::class, 'destroy'])->name('folders.destroy');
});
