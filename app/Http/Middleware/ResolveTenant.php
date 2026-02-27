<?php

namespace App\Http\Middleware;

use App\Services\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ResolveTenant
{
    public function __construct(private TenantManager $tenantManager) {}

    public function handle(Request $request, Closure $next): mixed
    {
        try {
            $this->tenantManager->resolveFromRequest($request->getHost());
        } catch (\Throwable) {
            // Pas de tenant — désactiver complètement le guard Auth
            // pour éviter que Laravel recharge un User depuis une connexion sans base
            config(['auth.defaults.guard' => 'null_guard']);
            config(['auth.guards.null_guard' => [
                'driver'   => 'session',
                'provider' => 'null_provider',
            ]]);
            config(['auth.providers.null_provider' => [
                'driver' => 'eloquent',
                'model'  => App\Models\Platform\Organization::class,
            ]]);
        }

        return $next($request);
    }
}
