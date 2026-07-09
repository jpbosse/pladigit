<?php

use App\Http\Middleware\CheckRole;
use App\Http\Middleware\CheckSuperAdmin;
use App\Http\Middleware\ForcePwdChange;
use App\Http\Middleware\GuardTenantSession;
use App\Http\Middleware\RequireGedPermission;
use App\Http\Middleware\RequireModule;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\ValidateWopiRequest;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // ResolveTenant doit être PREPEND (avant tout le reste) — nécessaire
        // pour que applySessionLifetime() règle config('session.lifetime')
        // AVANT que StartSession ne lise cette config.
        $middleware->prependToGroup('web', ResolveTenant::class);
        // GuardTenantSession doit s'exécuter APRÈS StartSession (accès à la
        // session requis) — donc ajoutée normalement, jamais prependée.
        // Corrige la faille d'isolation inter-tenants (cookie de session
        // partagé entre sous-domaines, cf. ADR isolation session).
        $middleware->appendToGroup('web', GuardTenantSession::class);
        // Alias personnalisés
        $middleware->alias([
            'tenant' => ResolveTenant::class,
            'role' => CheckRole::class,
            'super-admin' => CheckSuperAdmin::class,
            'force-pwd-change' => ForcePwdChange::class,
            'module' => RequireModule::class,
            'ged.permission' => RequireGedPermission::class,
            'wopi' => ValidateWopiRequest::class,
        ]);
        // Exemption CSRF pour le login cross-domaine (popup pladigit.fr → {slug}.pladigit.fr)
        // + routes WOPI : Collabora n'envoie pas de token CSRF
        $middleware->validateCsrfTokens(except: [
            'login',
            'wopi/*',
        ]);
    })

    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->create();
