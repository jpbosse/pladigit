<?php

namespace App\Http\Middleware;

use App\Models\Platform\Organization;
use App\Models\Tenant\TenantSettings;
use App\Services\TenantMailer;
use App\Services\TenantManager;
use Closure;
use Illuminate\Http\Request;

class ResolveTenant
{
    public function __construct(
        private TenantManager $tenantManager,
        private TenantMailer $tenantMailer,
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        if (app()->environment('testing') && $this->tenantManager->hasTenant()) {
            return $next($request);
        }

        try {
            $this->tenantManager->resolveFromRequest($request->getHost());
            \Log::info('ResolveTenant OK', ['host' => $request->getHost()]);

            if ($org = $this->tenantManager->current()) {
                $this->tenantMailer->configureForTenant($org);
            }

            $this->applySessionLifetime();
        } catch (\Throwable $e) {
            \Log::error('ResolveTenant FAIL', ['host' => $request->getHost(), 'error' => $e->getMessage()]);
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

    /**
     * Écrase config('session.lifetime') avec la valeur configurée par le tenant.
     * Défaut : valeur .env si non configuré ou zéro.
     */
    private function applySessionLifetime(): void
    {
        try {
            $settings = TenantSettings::first();
            if ($settings && $settings->session_lifetime_minutes > 0) {
                config(['session.lifetime' => $settings->session_lifetime_minutes]);
            }
        } catch (\Throwable) {
            // Tenant non résolu ou table absente → on garde la valeur .env
        }
    }
}
