<?php

use App\Livewire\Tenant\Datagrid\ImportWizard;

// ── DataGrid — ERP no-code (Phase 8) ───────────────────────────────────────
Route::prefix('datagrid')->name('datagrid.')->group(function () {

    Route::get('import', ImportWizard::class)
        ->name('import');

});
