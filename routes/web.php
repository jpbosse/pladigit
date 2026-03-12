<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SuperAdmin\OrganizationController;

// ── Page d'accueil publique ───────────────────────────────
Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::post('/contact', [App\Http\Controllers\ContactController::class, 'send'])
    ->name('contact.send');
Route::get('check-org/{slug}', function ($slug) {
    $exists = \DB::table('organizations')->where('slug', $slug)->exists();
    if ($exists) {
        return redirect()->away('http://'.$slug.'.pladigit.fr/login');
    }

    return back()->withErrors(['org' => 'Organisation introuvable. Vérifiez votre identifiant.']);
})->name('check.org');
Route::get('check-org-ajax/{slug}', function ($slug) {
    $exists = \DB::table('organizations')->where('slug', $slug)->exists();

    return response()->json(['exists' => $exists]);
});

// ── Super Admin — Login (sans middleware) ──────────────────
Route::get('super-admin/login', [App\Http\Controllers\SuperAdmin\AuthController::class, 'showLoginForm'])
    ->name('super-admin.login');
Route::post('super-admin/login', [App\Http\Controllers\SuperAdmin\AuthController::class, 'login'])
    ->middleware('throttle:super-admin-login')
    ->name('super-admin.login.post');
Route::post('super-admin/logout', [App\Http\Controllers\SuperAdmin\AuthController::class, 'logout'])
    ->name('super-admin.logout');

// ── Routes Super-Admin ─────────────────────────────────────
Route::prefix('super-admin')
    ->name('super-admin.')
    ->middleware('super-admin')
    ->group(function () {
        Route::get('/', [OrganizationController::class, 'index'])->name('dashboard');
        Route::resource('organizations', OrganizationController::class);
        Route::post('organizations/{organization}/suspend', [OrganizationController::class, 'suspend'])->name('organizations.suspend');
        Route::post('organizations/{organization}/activate', [OrganizationController::class, 'activate'])->name('organizations.activate');
        Route::post('organizations/{organization}/create-admin', [OrganizationController::class, 'createAdmin'])->name('organizations.create-admin');
        Route::post('organizations/{organization}/smtp', [OrganizationController::class, 'updateSmtp'])->name('organizations.update-smtp');
        Route::post('organizations/{organization}/ldap', [OrganizationController::class, 'updateLdap'])->name('organizations.update-ldap');
    });

// ── Routes Tenant ──────────────────────────────────────────
Route::middleware('tenant')->group(function () {

    // Authentification
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:login');
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/logout', fn () => redirect()->route('login'));

    Route::post('/profile/backup-codes', [App\Http\Controllers\ProfileController::class, 'regenerateBackupCodes'])->name('profile.regenerate-backup-codes');

    // 2FA — Challenge login
    // throttle:5,10 = 5 tentatives max par tranche de 10 minutes par IP
    Route::get('/2fa/challenge', [TwoFactorController::class, 'challenge'])->name('2fa.challenge');
    Route::post('/2fa/verify', [TwoFactorController::class, 'verify'])
        ->middleware('throttle:5,10')
        ->name('2fa.verify');

    // Invitation — activation de compte par email (routes publiques, pas d'auth)
    Route::get('/invitation/{token}', [App\Http\Controllers\Auth\InvitationController::class, 'show'])
        ->name('invitation.show');
    Route::post('/invitation/{token}', [App\Http\Controllers\Auth\InvitationController::class, 'accept'])
        ->middleware('throttle:5,10')
        ->name('invitation.accept');

    // Zone authentifiée — force-pwd-change appliqué sur TOUTES les routes auth
    Route::middleware(['auth', 'force-pwd-change'])->group(function () {

        // Changement de mot de passe forcé — accessible même avec force_pwd_change=1
        Route::get('/password/change', [App\Http\Controllers\Auth\PasswordChangeController::class, 'showForced'])->name('password.change.forced');
        Route::post('/password/change', [App\Http\Controllers\Auth\PasswordChangeController::class, 'updateForced'])->name('password.change.forced.update');

        // 2FA — gestion depuis le profil
        Route::get('/2fa/setup', [TwoFactorController::class, 'setup'])->name('2fa.setup');
        Route::post('/2fa/confirm', [TwoFactorController::class, 'confirm'])->name('2fa.confirm');
        Route::post('/2fa/disable', [TwoFactorController::class, 'disable'])->name('2fa.disable');

        // Profil utilisateur (§18.4)
        Route::get('/profile', [App\Http\Controllers\ProfileController::class, 'show'])->name('profile.show');
        Route::patch('/profile/info', [App\Http\Controllers\ProfileController::class, 'updateInfo'])->name('profile.update-info');
        Route::patch('/profile/password', [App\Http\Controllers\ProfileController::class, 'updatePassword'])->name('profile.update-password');

        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // ── Zone Admin Organisation ────────────────────────
        Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {

            // Utilisateurs
            Route::get('users', [App\Http\Controllers\Admin\UserController::class, 'index'])->name('users.index');
            Route::get('users/create', [App\Http\Controllers\Admin\UserController::class, 'create'])->name('users.create');
            Route::post('users', [App\Http\Controllers\Admin\UserController::class, 'store'])->name('users.store');
            Route::get('users/{user}/edit', [App\Http\Controllers\Admin\UserController::class, 'edit'])->name('users.edit');
            Route::put('users/{user}', [App\Http\Controllers\Admin\UserController::class, 'update'])->name('users.update');
            Route::delete('users/{user}', [App\Http\Controllers\Admin\UserController::class, 'destroy'])->name('users.destroy');
            Route::post('users/{user}/reset-password', [App\Http\Controllers\Admin\UserController::class, 'resetPassword'])->name('users.reset-password');

            Route::get('departments/organigramme', [App\Http\Controllers\Admin\DepartmentController::class, 'organigramme'])->name('departments.organigramme');
            Route::resource('departments', App\Http\Controllers\Admin\DepartmentController::class)
                ->only(['index', 'store', 'update', 'destroy'])
                ->names('departments');

            /*
             * Paramètres LDAP et SMTP — à décommenter en Phase 2 (§18.1)
             *
        */

            Route::get('settings/ldap', [App\Http\Controllers\Admin\SettingsController::class, 'ldap'])->name('settings.ldap');
            Route::put('settings/ldap', [App\Http\Controllers\Admin\SettingsController::class, 'updateLdap'])->name('settings.ldap.update');
            Route::get('settings/ldap/test', [App\Http\Controllers\Admin\SettingsController::class, 'testLdap'])->name('settings.ldap.test');
            Route::get('settings/smtp', [App\Http\Controllers\Admin\SettingsController::class, 'smtp'])->name('settings.smtp');
            Route::put('settings/smtp', [App\Http\Controllers\Admin\SettingsController::class, 'updateSmtp'])->name('settings.smtp.update');
            Route::post('settings/smtp/test', [App\Http\Controllers\Admin\SettingsController::class, 'testSmtp'])->name('settings.smtp.test');
            Route::get('settings/branding', [App\Http\Controllers\Admin\SettingsController::class, 'branding'])->name('settings.branding');
            Route::post('settings/branding', [App\Http\Controllers\Admin\SettingsController::class, 'updateBranding'])->name('settings.branding.update');
            Route::get('settings/media', [App\Http\Controllers\Admin\SettingsController::class, 'media'])->name('settings.media');
            Route::put('settings/media', [App\Http\Controllers\Admin\SettingsController::class, 'updateMedia'])->name('settings.media.update');
            Route::get('settings/nas', [App\Http\Controllers\Admin\SettingsController::class, 'nas'])->name('settings.nas');
            Route::put('settings/nas', [App\Http\Controllers\Admin\SettingsController::class, 'updateNas'])->name('settings.nas.update');
            Route::post('settings/nas/sync', [\App\Http\Controllers\Admin\SettingsController::class, 'syncNas'])->name('settings.nas.sync');

        });

        // ── Photothèque — accessible à tous les authentifiés ──
        Route::prefix('media')->name('media.')->group(function () {

            // Albums
            Route::get('albums', [\App\Http\Controllers\Media\MediaAlbumController::class, 'index'])->name('albums.index');
            Route::get('albums/create', [\App\Http\Controllers\Media\MediaAlbumController::class, 'create'])->name('albums.create');
            Route::post('albums', [\App\Http\Controllers\Media\MediaAlbumController::class, 'store'])->name('albums.store');
            Route::get('albums/{album}', [\App\Http\Controllers\Media\MediaAlbumController::class, 'show'])->name('albums.show');
            Route::get('albums/{album}/edit', [\App\Http\Controllers\Media\MediaAlbumController::class, 'edit'])->name('albums.edit');
            Route::put('albums/{album}', [\App\Http\Controllers\Media\MediaAlbumController::class, 'update'])->name('albums.update');
            Route::delete('albums/{album}', [\App\Http\Controllers\Media\MediaAlbumController::class, 'destroy'])->name('albums.destroy');

            // ── Droits par album ──────────────────────────────────────────
            Route::prefix('albums/{album}/permissions')
                ->name('albums.permissions.')
                ->controller(\App\Http\Controllers\Media\AlbumPermissionController::class)
                ->group(function () {
                    Route::get('/', 'edit')->name('edit');
                    Route::post('/subject', 'storeSubject')->name('store-subject');
                    Route::post('/user', 'storeUser')->name('store-user');
                    Route::delete('/subject/{permission}', 'destroySubject')->name('destroy-subject');
                    Route::delete('/user/{permission}', 'destroyUser')->name('destroy-user');
                });

            // Partages individuels par média
            Route::get('items/{item}/shares', [\App\Http\Controllers\Media\MediaItemShareController::class, 'edit'])->name('items.shares.edit');
            Route::post('items/{item}/shares', [\App\Http\Controllers\Media\MediaItemShareController::class, 'store'])->name('items.shares.store');
            Route::patch('items/{item}/shares/{share}', [\App\Http\Controllers\Media\MediaItemShareController::class, 'update'])->name('items.shares.update');
            Route::delete('items/{item}/shares/{share}', [\App\Http\Controllers\Media\MediaItemShareController::class, 'destroy'])->name('items.shares.destroy');

            // Test connexion NAS (AJAX — admin uniquement)
            Route::get('nas/test', [\App\Http\Controllers\Media\MediaAlbumController::class, 'testNasConnection'])->name('nas.test');

            // Médias (imbriqués sous album)
            Route::get('albums/{album}/upload', [\App\Http\Controllers\Media\MediaItemController::class, 'create'])->name('items.create');
            Route::post('albums/{album}/upload', [\App\Http\Controllers\Media\MediaItemController::class, 'store'])->name('items.store');
            Route::get('albums/{album}/items/{item}', [\App\Http\Controllers\Media\MediaItemController::class, 'show'])->name('items.show');
            Route::delete('albums/{album}/items/{item}', [\App\Http\Controllers\Media\MediaItemController::class, 'destroy'])->name('items.destroy');
            Route::post('prefs/cols', [\App\Http\Controllers\Media\MediaPreferenceController::class, 'setCols'])->name('prefs.cols');
            Route::patch('albums/{album}/items/{item}/caption', [\App\Http\Controllers\Media\MediaItemController::class, 'updateCaption'])->name('items.updateCaption');

            // Servir les fichiers (inline et téléchargement)
            Route::get('albums/{album}/items/{item}/serve/{type?}', [\App\Http\Controllers\Media\MediaItemController::class, 'serve'])->name('items.serve');
            Route::get('albums/{album}/items/{item}/download', [\App\Http\Controllers\Media\MediaItemController::class, 'download'])->name('items.download');
        });

        // ── Zone DGS et plus ──────────────────────────────
        Route::middleware('role:dgs')->group(function () {
            // Rapports, exports… (phases futures)
        });
    });
});
