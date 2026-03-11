<?php

namespace App\Providers;

use App\Models\Tenant\MediaAlbum;
use App\Models\Tenant\MediaItem;
use App\Policies\MediaAlbumPolicy;
use App\Policies\MediaItemPolicy;
use App\Services\TenantManager;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantManager::class);
    }

    public function boot(): void
    {
        Gate::policy(MediaAlbum::class, MediaAlbumPolicy::class);
        Gate::policy(MediaItem::class, MediaItemPolicy::class);

        // Alias courts pour les relations polymorphiques (table shares)
        Relation::morphMap([
            'media_album' => MediaAlbum::class,
            'media_item' => MediaItem::class,
            // Phase 5 : 'ged_document' => GedDocument::class,
            // Phase 5 : 'ged_folder'   => GedFolder::class,
        ]);
    }
}
