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

// ── Super Admin — Login (sans middleware) ──────────────────
Route::get('super-admin/login', [App\Http\Controllers\SuperAdmin\AuthController::class, 'showLoginForm'])
    ->name('super-admin.login');
Route::post('super-admin/login', [App\Http\Controllers\SuperAdmin\AuthController::class, 'login'])
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
    Route::post('/login', [LoginController::class, 'login']);
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::post('/profile/backup-codes', [App\Http\Controllers\ProfileController::class, 'regenerateBackupCodes'])->name('profile.regenerate-backup-codes');

    // 2FA — Challenge login
    // throttle:5,10 = 5 tentatives max par tranche de 10 minutes par IP
    Route::get('/2fa/challenge', [TwoFactorController::class, 'challenge'])->name('2fa.challenge');
    Route::post('/2fa/verify', [TwoFactorController::class, 'verify'])
        ->middleware('throttle:5,10')
        ->name('2fa.verify');

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

	Route::resource('departments', App\Http\Controllers\Admin\DepartmentController::class)
	     ->only(['index', 'store', 'update', 'destroy'])
	     ->names('departments');

            /*
             * Paramètres LDAP et SMTP — à décommenter en Phase 2 (§18.1)
             *
             * Route::get('settings/ldap', [App\Http\Controllers\Admin\SettingsController::class, 'ldap'])->name('settings.ldap');
             * Route::put('settings/ldap', [App\Http\Controllers\Admin\SettingsController::class, 'updateLdap'])->name('settings.ldap.update');
             * Route::get('settings/ldap/test', [App\Http\Controllers\Admin\SettingsController::class, 'testLdap'])->name('settings.ldap.test');
             * Route::get('settings/smtp', [App\Http\Controllers\Admin\SettingsController::class, 'smtp'])->name('settings.smtp');
             * Route::put('settings/smtp', [App\Http\Controllers\Admin\SettingsController::class, 'updateSmtp'])->name('settings.smtp.update');
             */

            // Personnalisation
            Route::get('settings/branding', [App\Http\Controllers\Admin\SettingsController::class, 'branding'])->name('settings.branding');
            Route::post('settings/branding', [App\Http\Controllers\Admin\SettingsController::class, 'updateBranding'])->name('settings.branding.update');
        });

        // ── Zone DGS et plus ──────────────────────────────
        Route::middleware('role:dgs')->group(function () {
            // Rapports, exports… (phases futures)
        });
    });
});
