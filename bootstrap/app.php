<?php

use App\Http\Middleware\CheckRole;
use App\Http\Middleware\CheckSuperAdmin;
use App\Http\Middleware\ForcePwdChange;
use App\Http\Middleware\ResolveTenant;
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
        // ResolveTenant doit être PREPEND (avant tout le reste)
        $middleware->prependToGroup('web', ResolveTenant::class);
        // Alias personnalisés
        $middleware->alias([
            'tenant' => ResolveTenant::class,
            'role' => CheckRole::class,
            'super-admin' => CheckSuperAdmin::class,
            'force-pwd-change' => ForcePwdChange::class,
            'module' => \App\Http\Middleware\RequireModule::class,
        ]);
        // Exemption CSRF pour le login cross-domaine (popup pladigit.fr → {slug}.pladigit.fr)
        $middleware->validateCsrfTokens(except: [
            'login',
        ]);
    })

    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->create();
