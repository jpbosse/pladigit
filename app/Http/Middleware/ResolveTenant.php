<?php

namespace App\Http\Middleware;

use App\Models\Platform\Organization;
use App\Services\TenantManager;
use Closure;
use Illuminate\Http\Request;

class ResolveTenant
{
    public function __construct(private TenantManager $tenantManager) {}

    public function handle(Request $request, Closure $next): mixed
    {
        // En test, le tenant est pré-résolu par TestCase::setUp()
        if (app()->environment('testing') && $this->tenantManager->hasTenant()) {
            return $next($request);
        }

        try {
            $this->tenantManager->resolveFromRequest($request->getHost());
        } catch (\Throwable) {
            config(['auth.defaults.guard' => 'null_guard']);
            config(['auth.guards.null_guard' => [
                'driver'   => 'session',
                'provider' => 'null_provider',
            ]]);
            config(['auth.providers.null_provider' => [
                'driver' => 'eloquent',
                'model'  => Organization::class,
            ]]);
        }

        return $next($request);
    }
}
