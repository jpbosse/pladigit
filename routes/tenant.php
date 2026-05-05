<?php

use App\Http\Controllers\Datagrid\DatagridAdminController;
use App\Http\Controllers\Datagrid\DatagridController;

// ── DataGrid — ERP no-code (Phase 8) ───────────────────────────────────────
Route::prefix('datagrid')->name('datagrid.')->middleware('module:datagrid')->group(function () {

    // Structure + import — admin orga uniquement
    // (admin group en premier pour éviter que GET {table} capture /import)
    Route::middleware('role:admin')->group(function () {
        Route::get('import', [DatagridController::class, 'import'])->name('import');
        Route::patch('{table}', [DatagridAdminController::class, 'update'])->name('update');
        Route::patch('{table}/columns/{column}', [DatagridController::class, 'updateColumn'])->name('columns.update');
        Route::delete('{table}/columns/{column}', [DatagridAdminController::class, 'destroyColumn'])->name('columns.destroy');
        Route::delete('{table}', [DatagridAdminController::class, 'destroy'])->name('destroy');
    });

    // Lecture — tous les authentifiés
    Route::get('/', [DatagridController::class, 'index'])->name('index');
    Route::get('{table}', [DatagridController::class, 'show'])->name('show');
    Route::post('{table}/views', [DatagridController::class, 'storeView'])->name('views.store');
    Route::delete('{table}/views/{view}', [DatagridController::class, 'destroyView'])->name('views.destroy');

});
