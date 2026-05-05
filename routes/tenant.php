<?php

use App\Http\Controllers\Datagrid\DatagridController;
use App\Livewire\Tenant\Datagrid\ImportWizard;

// ── DataGrid — ERP no-code (Phase 8) ───────────────────────────────────────
Route::prefix('datagrid')->name('datagrid.')->middleware('module:datagrid')->group(function () {

    Route::get('/', [DatagridController::class, 'index'])
        ->name('index');

    Route::get('import', ImportWizard::class)
        ->name('import');

    Route::get('{table}', [DatagridController::class, 'show'])
        ->name('show');

    Route::patch('{table}/columns/{column}', [DatagridController::class, 'updateColumn'])
        ->name('columns.update');

    Route::post('{table}/views', [DatagridController::class, 'storeView'])
        ->name('views.store');

    Route::delete('{table}/views/{view}', [DatagridController::class, 'destroyView'])
        ->name('views.destroy');

});
