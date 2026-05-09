<?php

use App\Http\Controllers\Datagrid\DatagridController;
use App\Http\Controllers\Datagrid\DatagridFolderController;
use App\Http\Controllers\Tenant\Admin\DatagridAdminController;

// ── DataGrid — ERP no-code (Phase 8) ───────────────────────────────────────
Route::prefix('datagrid')->name('datagrid.')->middleware('module:datagrid')->group(function () {

    // Structure + import — admin orga uniquement
    Route::middleware('role:admin')->group(function () {
        Route::get('import', [DatagridController::class, 'import'])->name('import');
        Route::patch('{table}', [DatagridAdminController::class, 'update'])->name('update');
        Route::patch('{table}/columns/{column}', [DatagridAdminController::class, 'updateColumn'])->name('columns.update');
        Route::delete('{table}/columns/{column}', [DatagridAdminController::class, 'destroyColumn'])->name('columns.destroy');
        Route::delete('{table}', [DatagridAdminController::class, 'destroy'])->name('destroy');

        // Dossiers — admin uniquement
        Route::post('folders', [DatagridFolderController::class, 'store'])->name('folder.store');
        Route::patch('folders/{folder}', [DatagridFolderController::class, 'update'])->name('folder.update');
        Route::delete('folders/{folder}', [DatagridFolderController::class, 'destroy'])->name('folder.destroy');

        // Déplacement d'une grille dans un dossier
        Route::patch('{table}/move', [DatagridFolderController::class, 'moveTable'])->name('table.move');
    });

    // Lecture — tous les authentifiés
    Route::get('/', [DatagridController::class, 'index'])->name('index');
    Route::get('{table}', [DatagridController::class, 'show'])->name('show');
    Route::post('{table}/views', [DatagridController::class, 'storeView'])->name('views.store');
    Route::delete('{table}/views/{view}', [DatagridController::class, 'destroyView'])->name('views.destroy');

});
