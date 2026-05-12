<?php

use App\Http\Controllers\Admin\AdminGedController;
use App\Http\Controllers\Admin\AdminPurgeController;
use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\DemoController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\ProjectReassignController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Ged\WopiController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\Media\AlbumPermissionController;
use App\Http\Controllers\Media\MediaAlbumController;
use App\Http\Controllers\Media\MediaDuplicateController;
use App\Http\Controllers\Media\MediaIntegrityController;
use App\Http\Controllers\Media\MediaItemController;
use App\Http\Controllers\Media\MediaItemShareController;
use App\Http\Controllers\Media\MediaItemTagController;
use App\Http\Controllers\Media\MediaPreferenceController;
use App\Http\Controllers\Media\MediaSearchController;
use App\Http\Controllers\Media\MediaShareLinkController;
use App\Http\Controllers\Media\SharedAlbumController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SuperAdmin\AuthController;
use App\Http\Controllers\SuperAdmin\BackupController;
use App\Http\Controllers\SuperAdmin\DatagridController;
use App\Http\Controllers\SuperAdmin\OrganizationController;
use App\Http\Controllers\SuperAdmin\SecurityController;
use App\Http\Controllers\SuperAdmin\StatsController;
use App\Http\Controllers\SuperAdmin\UpdateController;
use App\Http\Controllers\Tenant\Admin\DatagridAdminController;
use App\Http\Controllers\Tenant\DatagridPdfController;

// ── Page d'accueil publique ───────────────────────────────
Route::get('/health', [HealthController::class, 'check'])->name('health');
Route::get('/health/ping', [HealthController::class, 'ping'])->name('health.ping');
Route::get('/mentions-legales', [LegalController::class, 'mentions'])->name('legal.mentions');
Route::get('/confidentialite', [LegalController::class, 'confidentialite'])->name('legal.confidentialite');

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::post('/contact', [ContactController::class, 'send'])
    ->name('contact.send');
Route::get('check-org/{slug}', function ($slug) {
    $exists = DB::table('organizations')->where('slug', $slug)->exists();
    if ($exists) {
        return redirect()->away('http://'.$slug.'.pladigit.fr/login');
    }

    return back()->withErrors(['org' => 'Organisation introuvable. Vérifiez votre identifiant.']);
})->name('check.org');
Route::get('check-org-ajax/{slug}', function ($slug) {
    $exists = DB::table('organizations')->where('slug', $slug)->exists();

    return response()->json(['exists' => $exists]);
});

// ── WOPI — endpoints Collabora Online (URL fixe, hors middleware tenant) ──
// Le tenant est transmis dans le chemin : /wopi/{tenant}/files/{id}.
// Collabora ajoute toujours "?access_token=TOKEN" avec "?" même si le WOPISrc
// a déjà une query string — mettre le tenant dans le chemin évite ce double-"?".
// Collabora n'a besoin que d'un seul aliasgroup fixe (ex : https://pladigit.fr).
Route::prefix('wopi/{tenant}/files')->name('wopi.')->middleware('wopi')->group(function () {
    Route::get('{id}', [WopiController::class, 'checkFileInfo'])->name('files.info');
    Route::get('{id}/contents', [WopiController::class, 'getFile'])->name('files.contents');
    Route::post('{id}/contents', [WopiController::class, 'putFile'])->name('files.put');
    Route::post('{id}', [WopiController::class, 'lockFile'])->name('files.lock');
});

// ── Super Admin — Login (sans middleware) ──────────────────
Route::get('super-admin/login', [AuthController::class, 'showLoginForm'])
    ->name('super-admin.login');
Route::post('super-admin/login', [AuthController::class, 'login'])
    ->middleware('throttle:super-admin-login')
    ->name('super-admin.login.post');
Route::get('super-admin/login/totp', [AuthController::class, 'showTotpForm'])
    ->name('super-admin.login.totp');
Route::post('super-admin/login/totp', [AuthController::class, 'verifyTotp'])
    ->middleware('throttle:super-admin-login')
    ->name('super-admin.login.totp.verify');
Route::post('super-admin/logout', [AuthController::class, 'logout'])
    ->name('super-admin.logout');

// ── Routes Super-Admin ─────────────────────────────────────
Route::prefix('super-admin')
    ->name('super-admin.')
    ->middleware('super-admin')
    ->group(function () {
        Route::get('/', [OrganizationController::class, 'index'])->name('dashboard');
        Route::get('/stats', [StatsController::class, 'index'])->name('stats');
        Route::get('datagrids', [DatagridController::class, 'index'])->name('datagrids.index');
        Route::get('datagrids/{organization}/import', [DatagridController::class, 'import'])->name('datagrids.import');
        Route::get('datagrids/{organization}/{table}/edit', [DatagridController::class, 'edit'])->name('datagrids.edit');
        Route::patch('datagrids/{organization}/{table}', [DatagridController::class, 'update'])->name('datagrids.update');
        Route::delete('datagrids/{organization}/{table}', [DatagridController::class, 'destroy'])->name('datagrids.destroy');
        Route::delete('datagrids/{organization}/{table}/columns/{column}', [DatagridController::class, 'destroyColumn'])->name('datagrids.columns.destroy');
        Route::get('datagrids/{organization}/{table}/columns/{column}/edit', [DatagridController::class, 'editColumn'])->name('datagrids.columns.edit');
        Route::patch('datagrids/{organization}/{table}/columns/{column}', [DatagridController::class, 'updateColumn'])->name('datagrids.columns.update');
        Route::resource('organizations', OrganizationController::class);
        Route::post('organizations/{organization}/suspend', [OrganizationController::class, 'suspend'])->name('organizations.suspend');
        Route::post('organizations/{organization}/activate', [OrganizationController::class, 'activate'])->name('organizations.activate');
        Route::post('organizations/{organization}/create-admin', [OrganizationController::class, 'createAdmin'])->name('organizations.create-admin');
        Route::post('organizations/{organization}/smtp', [OrganizationController::class, 'updateSmtp'])->name('organizations.update-smtp');
        Route::post('organizations/{organization}/smtp/test', [OrganizationController::class, 'testSmtp'])->name('organizations.test-smtp');
        Route::post('organizations/{organization}/ldap', [OrganizationController::class, 'updateLdap'])->name('organizations.update-ldap');
        Route::post('organizations/{organization}/ldap/test', [OrganizationController::class, 'testLdap'])->name('organizations.test-ldap');
        Route::post('organizations/{organization}/modules', [OrganizationController::class, 'updateModules'])->name('organizations.update-modules');

        Route::get('update', [UpdateController::class, 'index'])->name('update');
        Route::post('update/run', [UpdateController::class, 'run'])->name('update.run');
        Route::get('update/status', [UpdateController::class, 'status'])->name('update.status');
        Route::get('update/check-version', [UpdateController::class, 'checkVersion'])->name('update.check-version');
        Route::get('update/log', [UpdateController::class, 'log'])->name('update.log');

        Route::get('backup', [BackupController::class, 'index'])->name('backup');
        Route::put('backup', [BackupController::class, 'update'])->name('backup.update');
        Route::post('backup/run', [BackupController::class, 'run'])->name('backup.run');
        Route::get('backup/status', [BackupController::class, 'status'])->name('backup.status');
        Route::get('backup/test-sftp', [BackupController::class, 'testSftp'])->name('backup.test-sftp');

        Route::get('security/totp', [SecurityController::class, 'totpSetup'])->name('security.totp');
        Route::post('security/totp', [SecurityController::class, 'totpConfirm'])->name('security.totp.confirm');
    });

// ── Routes Tenant ──────────────────────────────────────────
Route::middleware('tenant')->group(function () {

    // Authentification
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:login');
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/logout', fn () => redirect()->route('login'));

    Route::post('/profile/backup-codes', [ProfileController::class, 'regenerateBackupCodes'])->name('profile.regenerate-backup-codes');

    // 2FA — Challenge login
    // throttle:5,10 = 5 tentatives max par tranche de 10 minutes par IP
    Route::get('/2fa/challenge', [TwoFactorController::class, 'challenge'])->name('2fa.challenge');
    Route::post('/2fa/verify', [TwoFactorController::class, 'verify'])
        ->middleware('throttle:5,10')
        ->name('2fa.verify');

    // Supprimé — routes WOPI déplacées hors du groupe tenant (voir ci-dessous)

    // Liens de partage temporaires — accès public (pas d'auth requise)
    Route::get('/s/{token}', [SharedAlbumController::class, 'show'])->name('media.shared.show');
    Route::post('/s/{token}/verify', [SharedAlbumController::class, 'authenticate'])->name('media.shared.auth');
    Route::get('/s/{token}/items/{itemId}/serve/{type?}', [SharedAlbumController::class, 'serveItem'])->name('media.shared.serve');
    Route::get('/s/{token}/items/{itemId}/download', [SharedAlbumController::class, 'downloadItem'])->name('media.shared.download');
    Route::get('/s/{token}/export-zip', [SharedAlbumController::class, 'exportZip'])->name('media.shared.export-zip');

    // Invitation — activation de compte par email (routes publiques, pas d'auth)
    Route::get('/invitation/{token}', [InvitationController::class, 'show'])
        ->name('invitation.show');
    Route::post('/invitation/{token}', [InvitationController::class, 'accept'])
        ->middleware('throttle:5,10')
        ->name('invitation.accept');

    // Zone authentifiée — force-pwd-change appliqué sur TOUTES les routes auth
    Route::middleware(['auth', 'force-pwd-change'])->group(function () {

        // Changement de mot de passe forcé — accessible même avec force_pwd_change=1
        Route::get('/password/change', [PasswordChangeController::class, 'showForced'])->name('password.change.forced');
        Route::post('/password/change', [PasswordChangeController::class, 'updateForced'])->name('password.change.forced.update');

        // 2FA — gestion depuis le profil
        Route::get('/2fa/setup', [TwoFactorController::class, 'setup'])->name('2fa.setup');
        Route::post('/2fa/confirm', [TwoFactorController::class, 'confirm'])->name('2fa.confirm');
        Route::post('/2fa/disable', [TwoFactorController::class, 'disable'])->name('2fa.disable');

        // Profil utilisateur (§18.4)
        Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
        Route::patch('/profile/info', [ProfileController::class, 'updateInfo'])->name('profile.update-info');
        Route::patch('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.update-password');
        Route::patch('/profile/preferences', [ProfileController::class, 'updatePreferences'])->name('profile.update-preferences');

        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // ── Notifications ──────────────────────────────────
        Route::prefix('notifications')->name('notifications.')->group(function () {
            Route::get('/', [NotificationController::class, 'index'])->name('index');
            Route::patch('/{notification}', [NotificationController::class, 'markRead'])->name('read');
            Route::post('/read-all', [NotificationController::class, 'markAllRead'])->name('read-all');
            Route::delete('/{notification}', [NotificationController::class, 'destroy'])->name('destroy');
        });

        // ── Zone Admin Organisation ────────────────────────
        Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {

            // Utilisateurs
            Route::get('users', [UserController::class, 'index'])->name('users.index');
            Route::get('users/create', [UserController::class, 'create'])->name('users.create');
            Route::post('users', [UserController::class, 'store'])->name('users.store');
            Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
            Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
            Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
            Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
            Route::post('users/{user}/reset-2fa', [UserController::class, 'reset2fa'])->name('users.reset-2fa');

            Route::get('departments/organigramme', [DepartmentController::class, 'organigramme'])->name('departments.organigramme');
            Route::resource('departments', DepartmentController::class)
                ->only(['index', 'store', 'update', 'destroy'])
                ->names('departments');

            /*
             * Paramètres LDAP et SMTP — à décommenter en Phase 2 (§18.1)
             *
        */

            Route::get('settings/ldap', [SettingsController::class, 'ldap'])->name('settings.ldap');
            Route::put('settings/ldap', [SettingsController::class, 'updateLdap'])->name('settings.ldap.update');
            Route::get('settings/ldap/test', [SettingsController::class, 'testLdap'])->name('settings.ldap.test');
            Route::get('settings/smtp', [SettingsController::class, 'smtp'])->name('settings.smtp');
            Route::put('settings/smtp', [SettingsController::class, 'updateSmtp'])->name('settings.smtp.update');
            Route::post('settings/smtp/test', [SettingsController::class, 'testSmtp'])->name('settings.smtp.test');
            Route::get('settings/branding', [SettingsController::class, 'branding'])->name('settings.branding');
            Route::post('settings/branding', [SettingsController::class, 'updateBranding'])->name('settings.branding.update');
            Route::get('settings/media', [SettingsController::class, 'media'])->name('settings.media');
            Route::put('settings/media', [SettingsController::class, 'updateMedia'])->name('settings.media.update');
            Route::get('settings/nas', [SettingsController::class, 'nas'])->name('settings.nas');
            Route::put('settings/nas', [SettingsController::class, 'updateNas'])->name('settings.nas.update');
            Route::post('settings/nas/sync', [SettingsController::class, 'syncNas'])->name('settings.nas.sync');
            Route::get('settings/ged', [SettingsController::class, 'ged'])->name('settings.ged');
            Route::put('settings/ged', [SettingsController::class, 'updateGed'])->name('settings.ged.update');
            Route::get('settings/ged/test', [SettingsController::class, 'testGed'])->name('settings.ged.test');
            Route::post('settings/ged/sync', [SettingsController::class, 'syncGed'])->name('settings.ged.sync');
            Route::get('settings/collabora', [SettingsController::class, 'collabora'])->name('settings.collabora');
            Route::put('settings/collabora', [SettingsController::class, 'updateCollabora'])->name('settings.collabora.update');
            Route::get('settings/collabora/test', [SettingsController::class, 'testCollabora'])->name('settings.collabora.test');
            Route::get('settings/visio', [SettingsController::class, 'visio'])->name('settings.visio');
            Route::put('settings/visio', [SettingsController::class, 'updateVisio'])->name('settings.visio.update');
            Route::get('settings/security', [SettingsController::class, 'security'])->name('settings.security');
            Route::put('settings/security', [SettingsController::class, 'updateSecurity'])->name('settings.security.update');
            Route::get('settings/backup', [SettingsController::class, 'backup'])->name('settings.backup');
            Route::put('settings/backup', [SettingsController::class, 'updateBackup'])->name('settings.backup.update');
            Route::post('settings/backup/run', [SettingsController::class, 'runBackup'])->name('settings.backup.run');
            Route::get('settings/backup/status', [SettingsController::class, 'backupStatus'])->name('settings.backup.status');
            Route::get('settings/backup/test-sftp', [SettingsController::class, 'testBackupSftp'])->name('settings.backup.test-sftp');

            // DataGrid — gestion des grilles
            Route::middleware('module:datagrid')->group(function () {
                Route::get('datagrid', [DatagridAdminController::class, 'index'])->name('datagrid.index');
                Route::get('datagrid/{table}/edit', [DatagridAdminController::class, 'edit'])->name('datagrid.edit');
                Route::patch('datagrid/{table}', [DatagridAdminController::class, 'update'])->name('datagrid.update');
                Route::delete('datagrid/{table}', [DatagridAdminController::class, 'destroy'])->name('datagrid.destroy');
                Route::get('datagrid/{table}/columns/{column}/edit', [DatagridAdminController::class, 'editColumn'])->name('datagrid.columns.edit');
                Route::patch('datagrid/{table}/columns/{column}', [DatagridAdminController::class, 'updateColumn'])->name('datagrid.columns.update');
                // Droits d'accès (2.12)
                Route::post('datagrid/{table}/permissions/role', [DatagridAdminController::class, 'storeRolePermission'])->name('datagrid.permissions.role.store');
                Route::delete('datagrid/{table}/permissions/role/{permission}', [DatagridAdminController::class, 'destroyRolePermission'])->name('datagrid.permissions.role.destroy');
                Route::post('datagrid/{table}/permissions/department', [DatagridAdminController::class, 'storeDeptPermission'])->name('datagrid.permissions.dept.store');
                Route::delete('datagrid/{table}/permissions/department/{permission}', [DatagridAdminController::class, 'destroyDeptPermission'])->name('datagrid.permissions.dept.destroy');
                Route::post('datagrid/{table}/permissions/user', [DatagridAdminController::class, 'storeUserPermission'])->name('datagrid.permissions.user.store');
                Route::delete('datagrid/{table}/permissions/user/{permission}', [DatagridAdminController::class, 'destroyUserPermission'])->name('datagrid.permissions.user.destroy');
            });

            // Purge GED — réservé au module GED
            Route::middleware('module:ged')->group(function () {
                Route::get('purge', [AdminPurgeController::class, 'index'])->name('purge.index');
                Route::put('purge/config', [AdminPurgeController::class, 'updateConfig'])->name('purge.config.update');
                Route::post('purge/preview', [AdminPurgeController::class, 'preview'])->name('purge.preview');
                Route::post('purge/run', [AdminPurgeController::class, 'run'])->name('purge.run');
            });

            // Réaffectation projets
            Route::middleware('module:projects')->group(function () {
                Route::get('projects/reassign', [ProjectReassignController::class, 'index'])->name('projects.reassign.index');
                Route::post('projects/reassign', [ProjectReassignController::class, 'store'])->name('projects.reassign.store');
                Route::post('projects/reassign/unowned', [ProjectReassignController::class, 'storeUnowned'])->name('projects.reassign.unowned');
            });

            // Journal d'audit
            Route::get('audit', [AuditController::class, 'index'])->name('audit.index');
            Route::get('audit/stats', [AuditController::class, 'stats'])->name('audit.stats');
            Route::get('audit/retention', [AuditController::class, 'retention'])->name('audit.retention.index');
            Route::patch('audit/retention', [AuditController::class, 'updateRetention'])->name('audit.retention.update');
            Route::delete('audit/purge', [AuditController::class, 'purge'])->name('audit.purge');
            Route::get('audit/export', [AuditController::class, 'export'])->name('audit.export');
            Route::get('audit/export/form', fn () => view('admin.audit.export'))->name('audit.export.form');

            // Gestion démo — uniquement pour l'organisation "demo"
            Route::get('demo', [DemoController::class, 'index'])->name('demo.index');
            Route::post('demo/photos', [DemoController::class, 'uploadPhotos'])->name('demo.photos.upload');
            Route::post('demo/ged', [DemoController::class, 'uploadGed'])->name('demo.ged.upload');
            Route::delete('demo/file', [DemoController::class, 'deleteFile'])->name('demo.file.delete');
            Route::post('demo/reset', [DemoController::class, 'reset'])->name('demo.reset');

        });

        // ── Photothèque — module activable par organisation ──
        Route::prefix('media')->name('media.')->middleware('module:media')->group(function () {

            // Doublons (admin / président / dgs uniquement — contrôleur vérifie le rôle)
            Route::get('duplicates', [MediaDuplicateController::class, 'index'])->name('duplicates.index');
            Route::post('duplicates/destroy', [MediaDuplicateController::class, 'destroySelected'])->name('duplicates.destroy');

            // Intégrité NAS ↔ BDD (admin / président / dgs uniquement — contrôleur vérifie le rôle)
            Route::get('integrity', [MediaIntegrityController::class, 'index'])->name('integrity.index');
            Route::post('integrity/scan', [MediaIntegrityController::class, 'scan'])->name('integrity.scan');
            Route::post('integrity/purge', [MediaIntegrityController::class, 'purgeDbOrphans'])->name('integrity.purge');
            Route::post('integrity/purge-soft', [MediaIntegrityController::class, 'purgeDbSoftDeleted'])->name('integrity.purge-soft');
            Route::post('integrity/purge-soft-albums', [MediaIntegrityController::class, 'purgeDbSoftAlbums'])->name('integrity.purge-soft-albums');
            Route::post('integrity/purge-albums', [MediaIntegrityController::class, 'purgeOrphanAlbums'])->name('integrity.purge-albums');
            Route::post('integrity/purge-share-links', [MediaIntegrityController::class, 'purgeOrphanShareLinks'])->name('integrity.purge-share-links');
            Route::post('integrity/purge-db-duplicates', [MediaIntegrityController::class, 'purgeDbDuplicates'])->name('integrity.purge-db-duplicates');

            // Albums
            Route::get('albums', [MediaAlbumController::class, 'index'])->name('albums.index');
            Route::get('search', [MediaSearchController::class, 'index'])->name('search');
            Route::get('albums/search', [MediaAlbumController::class, 'search'])->name('albums.search');
            Route::get('albums/{album}/children', [MediaAlbumController::class, 'children'])->name('albums.children');
            Route::get('albums/create', [MediaAlbumController::class, 'create'])->name('albums.create');
            Route::post('albums', [MediaAlbumController::class, 'store'])->name('albums.store');
            Route::get('albums/{album}', [MediaAlbumController::class, 'show'])->name('albums.show');
            Route::get('albums/{album}/edit', [MediaAlbumController::class, 'edit'])->name('albums.edit');
            Route::put('albums/{album}', [MediaAlbumController::class, 'update'])->name('albums.update');
            Route::delete('albums/{album}', [MediaAlbumController::class, 'destroy'])->name('albums.destroy');
            Route::put('albums/{album}/cover/{item}', [MediaAlbumController::class, 'setCover'])->name('albums.cover');
            Route::delete('albums/{album}/cover', [MediaAlbumController::class, 'resetCover'])->name('albums.cover.reset');

            // ── Droits par album ──────────────────────────────────────────
            Route::prefix('albums/{album}/permissions')
                ->name('albums.permissions.')
                ->controller(AlbumPermissionController::class)
                ->group(function () {
                    Route::get('/', 'edit')->name('edit');
                    Route::post('/subject', 'storeSubject')->name('store-subject');
                    Route::post('/user', 'storeUser')->name('store-user');
                    Route::delete('/subject/{permission}', 'destroySubject')->name('destroy-subject');
                    Route::delete('/user/{permission}', 'destroyUser')->name('destroy-user');
                });

            // Tags
            Route::post('items/{item}/tags', [MediaItemTagController::class, 'store'])->name('items.tags.store');
            Route::delete('items/{item}/tags/{tag}', [MediaItemTagController::class, 'destroy'])->name('items.tags.destroy');
            Route::get('tags/suggest', [MediaItemTagController::class, 'suggest'])->name('tags.suggest');

            // Partages individuels par média
            Route::get('items/{item}/shares', [MediaItemShareController::class, 'edit'])->name('items.shares.edit');
            Route::post('items/{item}/shares', [MediaItemShareController::class, 'store'])->name('items.shares.store');
            Route::patch('items/{item}/shares/{share}', [MediaItemShareController::class, 'update'])->name('items.shares.update');
            Route::delete('items/{item}/shares/{share}', [MediaItemShareController::class, 'destroy'])->name('items.shares.destroy');

            // Test connexion NAS (AJAX — admin uniquement)
            Route::get('nas/test', [MediaAlbumController::class, 'testNasConnection'])->name('nas.test');

            // Médias (imbriqués sous album)
            Route::get('albums/{album}/export-zip', [MediaAlbumController::class, 'exportZip'])->name('albums.export-zip');
            Route::get('albums/{album}/share-links', [MediaShareLinkController::class, 'index'])->name('albums.share-links.index');
            Route::post('albums/{album}/share-links', [MediaShareLinkController::class, 'store'])->name('albums.share-links.store');
            Route::delete('albums/{album}/share-links/{link}', [MediaShareLinkController::class, 'destroy'])->name('albums.share-links.destroy');
            Route::get('albums/{album}/upload', [MediaItemController::class, 'create'])->name('items.create');
            Route::post('albums/{album}/upload', [MediaItemController::class, 'store'])->name('items.store');
            Route::post('albums/{album}/import-zip', [MediaItemController::class, 'importZip'])->name('items.import-zip');
            Route::get('albums/{album}/items/{item}', [MediaItemController::class, 'show'])->name('items.show');
            Route::delete('albums/{album}/items/{item}', [MediaItemController::class, 'destroy'])->name('items.destroy');
            Route::post('prefs/cols', [MediaPreferenceController::class, 'setCols'])->name('prefs.cols');
            Route::post('sync', [MediaAlbumController::class, 'syncNas'])->name('sync');
            Route::patch('albums/{album}/items/{item}/caption', [MediaItemController::class, 'updateCaption'])->name('items.updateCaption');
            Route::post('albums/{album}/items/{item}/rotate', [MediaItemController::class, 'rotate'])->name('items.rotate');
            Route::post('albums/{album}/items/{item}/crop', [MediaItemController::class, 'crop'])->name('items.crop');
            Route::post('albums/{album}/items/move', [MediaItemController::class, 'moveItems'])->name('items.move');
            Route::post('albums/{album}/move-album', [MediaAlbumController::class, 'moveAlbum'])->name('albums.move');

            // Servir les fichiers (inline et téléchargement)
            Route::get('albums/{album}/items/{item}/serve/{type?}', [MediaItemController::class, 'serve'])->name('items.serve');
            Route::get('albums/{album}/items/{item}/download', [MediaItemController::class, 'download'])->name('items.download');

        });

        // ── Zone DGS et plus ──────────────────────────────
        Route::middleware('role:dgs')->group(function () {
            // Gouvernance GED (transfert propriété, orphelins)
            Route::middleware('module:ged')->prefix('admin')->name('admin.')->group(function () {
                Route::get('ged', [AdminGedController::class, 'index'])->name('ged.index');
                Route::post('ged/transfer-ownership', [AdminGedController::class, 'transferOwnership'])->name('ged.transfer-ownership');
            });
        });

        require base_path('routes/projects.php');

        // ── GED — Gestion Électronique de Documents ───────────
        require base_path('routes/ged.php');

        // ── DataGrid — ERP no-code ─────────────────────────────
        require base_path('routes/tenant.php');

    });

    // Sert le script bash d'installation
    Route::get('/install.sh', function () {
        $path = base_path('install.sh');
        if (! file_exists($path)) {
            abort(404, 'Fichier non trouvé.');
        }

        return response()->file($path, [
            'Content-Type' => 'text/x-sh',
            'Content-Disposition' => 'attachment; filename="install.sh"',
        ]);
    })->name('install.script');

    // Sert le wizard PHP (téléchargement)
    // Niveau 3 : bloque si .lock existe (installation déjà effectuée sur ce serveur)
    Route::get('/install-wizard.php', function () {
        // Si Pladigit est déjà installé sur CE serveur, bloquer le téléchargement
        if (file_exists(base_path('install/.lock'))) {
            return response()->view('errors.install-locked', [], 403);
        }

        $path = base_path('install/index.php');
        if (! file_exists($path)) {
            abort(404, 'Fichier non trouvé.');
        }

        return response()->file($path, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="index.php"',
        ]);
    })->name('install.wizard');

    // Route guide d'installation — sert le HTML directement
    Route::get('/guide-installation', function () {
        $path = base_path('docs/GUIDE-INSTALLATION.html');
        if (! file_exists($path)) {
            abort(404, 'Guide non trouvé.');
        }

        return response()->file($path, [
            'Content-Type' => 'text/html; charset=utf-8',
        ]);
    })->name('guide.installation');

    // Route Laravel pour get-collabora-installer
    Route::get('/get-collabora-installer', function () {
        $path = base_path('install/install-collabora.sh');
        if (! file_exists($path)) {
            abort(404);
        }

        return response()->file($path, [
            'Content-Type' => 'text/x-sh',
            'Content-Disposition' => 'inline; filename="install-collabora.sh"',
        ]);
    })->name('install.collabora');

});

// PDF Datagrid
Route::middleware(['auth', 'tenant'])->group(function () {
    Route::get('/datagrid/{table}/pdf/fiche/{rowId}', [DatagridPdfController::class, 'fiche'])->name('datagrid.pdf.fiche');
    Route::get('/datagrid/{table}/pdf/liste', [DatagridPdfController::class, 'liste'])->name('datagrid.pdf.liste');
});
