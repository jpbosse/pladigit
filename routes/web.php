<?php
 
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SuperAdmin\OrganizationController;
use Illuminate\Support\Facades\Route;

// Super Admin — Login (sans middleware)
Route::get('super-admin/login', [App\Http\Controllers\SuperAdmin\AuthController::class, 'showLoginForm'])
     ->name('super-admin.login');
Route::post('super-admin/login', [App\Http\Controllers\SuperAdmin\AuthController::class, 'login'])
     ->name('super-admin.login.post');
Route::post('super-admin/logout', [App\Http\Controllers\SuperAdmin\AuthController::class, 'logout'])
     ->name('super-admin.logout');
 
// ── Routes Super-Admin (pas de middleware tenant) ─────────
Route::prefix('super-admin')
     ->name('super-admin.')
     ->middleware('super-admin')
     ->group(function () {
    Route::get('/', [OrganizationController::class, 'index'])
         ->name('dashboard');
    Route::resource('organizations', OrganizationController::class);
    Route::post('organizations/{organization}/suspend',
                [OrganizationController::class, 'suspend'])
         ->name('organizations.suspend');
    Route::post('organizations/{organization}/create-admin', [OrganizationController::class, 'createAdmin'])->name('organizations.create-admin');
    Route::post('organizations/{organization}/activate',
                [OrganizationController::class, 'activate'])
         ->name('organizations.activate');
});
 
// ── Routes Tenant (avec résolution du tenant) ─────────────
Route::middleware('tenant')->group(function () {
 
    // Authentification
    Route::get('/login',  [LoginController::class, 'showLoginForm'])
         ->name('login');
    Route::post('/login', [LoginController::class, 'login']);
    Route::post('/logout',[LoginController::class, 'logout'])
         ->name('logout');
 
    // 2FA (Phase 2)
    Route::get('/2fa/challenge',  [TwoFactorController::class, 'challenge'])
         ->name('2fa.challenge');
    Route::post('/2fa/verify',    [TwoFactorController::class, 'verify'])
         ->name('2fa.verify');
 
    // Zone authentifiée
    Route::middleware('auth')->group(function () {
 
        // 2FA — gestion depuis le profil
        Route::get('/2fa/setup',    [TwoFactorController::class, 'setup'])
             ->name('2fa.setup');
        Route::post('/2fa/confirm', [TwoFactorController::class, 'confirm'])
             ->name('2fa.confirm');
        Route::post('/2fa/disable', [TwoFactorController::class, 'disable'])
             ->name('2fa.disable');
 
        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index'])
             ->name('dashboard');
 
        // Zone Admin Organisation
        Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
            // Gestion utilisateurs, paramètres LDAP, politique sécurité…
        });
 
        // Zone DGS et plus
        Route::middleware('role:dgs')->group(function () {
            // Rapports, exports…
        });
    });
});
