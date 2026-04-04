<?php

use App\Http\Controllers\Ged\GedDocumentController;
use App\Http\Controllers\Ged\GedFolderController;
use App\Http\Controllers\Ged\GedIntegrityController;
use App\Http\Controllers\Ged\GedPermissionController;

/*
|--------------------------------------------------------------------------
| GED — Gestion Électronique de Documents (Phase 6)
|--------------------------------------------------------------------------
| Toutes ces routes sont incluses dans web.php sous le groupe
| middleware(['auth', 'force-pwd-change']) + middleware('module:ged').
*/

Route::prefix('ged')->name('ged.')->middleware('module:ged')->group(function () {

    // ── Dossiers ─────────────────────────────────────────────
    Route::get('/', [GedFolderController::class, 'index'])->name('index');

    Route::get('folders/{folder}', [GedFolderController::class, 'show'])->name('folders.show');
    Route::get('folders/{folder}/children', [GedFolderController::class, 'children'])->name('folders.children');

    Route::post('folders', [GedFolderController::class, 'store'])->name('folders.store');
    Route::put('folders/{folder}', [GedFolderController::class, 'update'])->name('folders.update');
    Route::post('folders/{folder}/move', [GedFolderController::class, 'move'])->name('folders.move');
    Route::delete('folders/{folder}', [GedFolderController::class, 'destroy'])->name('folders.destroy');

    // ── Documents ────────────────────────────────────────────
    Route::post('documents', [GedDocumentController::class, 'store'])->name('documents.store');
    Route::get('documents/{document}/download', [GedDocumentController::class, 'download'])->name('documents.download');
    Route::get('documents/{document}/serve', [GedDocumentController::class, 'serve'])->name('documents.serve');
    Route::delete('documents/{document}', [GedDocumentController::class, 'destroy'])->name('documents.destroy');

    // Versioning
    Route::get('documents/{document}/versions', [GedDocumentController::class, 'versions'])->name('documents.versions');
    Route::get('documents/{document}/versions/{version}/download', [GedDocumentController::class, 'downloadVersion'])->name('documents.versions.download');
    Route::post('documents/{document}/versions/{version}/restore', [GedDocumentController::class, 'restoreVersion'])->name('documents.versions.restore');

    // ── Permissions dossier ──────────────────────────────────
    Route::get('folders/{folder}/permissions', [GedPermissionController::class, 'index'])->name('permissions.index');
    Route::post('folders/{folder}/permissions/role', [GedPermissionController::class, 'setRole'])->name('permissions.set-role');
    Route::post('folders/{folder}/permissions/department', [GedPermissionController::class, 'setDepartment'])->name('permissions.set-department');
    Route::post('folders/{folder}/permissions/user', [GedPermissionController::class, 'setUser'])->name('permissions.set-user');
    Route::delete('folders/{folder}/permissions/subject', [GedPermissionController::class, 'destroySubject'])->name('permissions.destroy-subject');
    Route::delete('folders/{folder}/permissions/user', [GedPermissionController::class, 'destroyUser'])->name('permissions.destroy-user');

    // ── Intégrité ────────────────────────────────────────────
    Route::get('integrity', [GedIntegrityController::class, 'index'])->name('integrity.index');
    Route::post('integrity/scan', [GedIntegrityController::class, 'scan'])->name('integrity.scan');
    Route::post('integrity/purge-db-orphans', [GedIntegrityController::class, 'purgeDbOrphans'])->name('integrity.purge-db-orphans');
    Route::post('integrity/purge-version-orphans', [GedIntegrityController::class, 'purgeVersionOrphans'])->name('integrity.purge-version-orphans');
    Route::post('integrity/purge-soft-docs', [GedIntegrityController::class, 'purgeSoftDocs'])->name('integrity.purge-soft-docs');
    Route::post('integrity/purge-soft-folders', [GedIntegrityController::class, 'purgeSoftFolders'])->name('integrity.purge-soft-folders');
    Route::post('integrity/purge-storage-orphans', [GedIntegrityController::class, 'purgeStorageOrphans'])->name('integrity.purge-storage-orphans');
});
