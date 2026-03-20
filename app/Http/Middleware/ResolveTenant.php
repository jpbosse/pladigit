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
        // Routes publiques sans tenant — priorité absolue, même en test
        if ($request->is('health', 'health/*')) {
            return $next($request);
        }

        // En test, le tenant est pré-résolu par TestCase::setUp()
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
                'driver' => 'session',
                'provider' => 'null_provider',
            ]]);
            config(['auth.providers.null_provider' => [
                'driver' => 'eloquent',
                'model' => Organization::class,
            ]]);
        }

        // Partager le compteur de notifications non lues sur toutes les vues
        if (auth()->check()) {
            try {
                $notifCount = \App\Models\Tenant\Notification::on('tenant')
                    ->where('user_id', auth()->id())
                    ->where('read', false)
                    ->count();
                \Illuminate\Support\Facades\View::share('notifCount', $notifCount);
            } catch (\Throwable) {
                \Illuminate\Support\Facades\View::share('notifCount', 0);
            }

            // Partager les données de stockage pour la topbar
            try {
                $media = \Illuminate\Support\Facades\DB::connection('tenant')
                    ->table('media_items')
                    ->whereNull('deleted_at')
                    ->selectRaw('SUM(file_size_bytes) as total, COUNT(*) as cnt')
                    ->first();

                $org = \App\Services\TenantManager::current();
                $quotaMb = $org?->storage_quota_mb ?? 10240;
                $usedBytes = (int) ($media->total ?? 0);
                $usedMb = round($usedBytes / 1024 / 1024, 1);

                \Illuminate\Support\Facades\View::share('storageByModule', [
                    'media' => $usedBytes,
                    'media_count' => (int) ($media->cnt ?? 0),
                    'ged' => 0, 'ged_count' => 0,
                    'erp' => 0, 'erp_tables' => 0, 'erp_rows' => 0,
                    'chat' => 0, 'chat_files' => 0,
                ]);
                \Illuminate\Support\Facades\View::share('storageUsedMb', $usedMb);
                \Illuminate\Support\Facades\View::share('storageQuotaMb', $quotaMb);
                \Illuminate\Support\Facades\View::share('storageUsedPct',
                    $quotaMb > 0 ? min(100, round($usedMb / $quotaMb * 100)) : 0
                );
            } catch (\Throwable) {
                \Illuminate\Support\Facades\View::share('storageByModule', null);
            }
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
