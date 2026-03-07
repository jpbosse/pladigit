<?php

namespace App\Providers;

use App\Models\Tenant\MediaAlbum;
use App\Policies\MediaAlbumPolicy;
use App\Services\TenantManager;
use Illuminate\Support\Facades\Gate;
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
        Gate::policy(MediaAlbum::class, MediaAlbumPolicy::class);
    }
}
