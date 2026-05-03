<?php

// ── DataGrid — ERP no-code (Phase 8) ───────────────────────────────────────
Route::prefix('datagrid')->name('datagrid.')->group(function () {

    Route::get('import', \App\Livewire\Tenant\Datagrid\ImportWizard::class)
        ->name('import');

});
