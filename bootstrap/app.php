<?php

use App\Http\Middleware\CheckRole;
use App\Http\Middleware\CheckSuperAdmin;
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
         ]);
     })
     ->withExceptions(function (Exceptions $exceptions) {
         //
     })
     ->create();
