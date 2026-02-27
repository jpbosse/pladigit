<?php

namespace App\Providers;

use App\Services\TenantManager;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Un seul TenantManager par requête HTTP
        $this->app->singleton(TenantManager::class);
    }

    public function boot(): void
    {
        //
    }
}
