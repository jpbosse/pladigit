<?php

namespace App\Providers;

use App\Models\Tenant\MediaAlbum;
use App\Models\Tenant\MediaItem;
use App\Policies\MediaAlbumPolicy;
use App\Policies\MediaItemPolicy;
use App\Services\TenantManager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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

        $this->configureRateLimiters();

        // Alias courts pour les relations polymorphiques (table shares)
        Relation::morphMap([
            'media_album' => MediaAlbum::class,
            'media_item' => MediaItem::class,
            // Phase 5 : 'ged_document' => GedDocument::class,
            // Phase 5 : 'ged_folder'   => GedFolder::class,
        ]);
    }

    /**
     * Définit les RateLimiters nommés utilisés via middleware throttle:<nom>.
     *
     * Stratégie credential stuffing / DDoS login :
     *  - 'login'            : double clé IP + email → bloque les attaques par dictionnaire
     *                         ciblant un compte précis depuis des IPs variées (credential stuffing)
     *                         ET les attaques depuis une IP unique sur de nombreux comptes.
     *  - 'login-ip'         : clé IP seule → filet de sécurité global, stoppe les bots
     *                         qui tournent sur une IP fixe avant même d'atteindre la couche email.
     *  - 'super-admin-login': seuil très bas, spécifique au backoffice super-admin.
     *
     * Ces limiteurs complètent le limit_req_zone Nginx qui bloque en amont (couche réseau)
     * avant que PHP-FPM ne soit sollicité.
     */
    private function configureRateLimiters(): void
    {
        // --- Limiter principal : couple IP + email ---
        // 10 tentatives sur 5 minutes par couple unique (IP, email).
        // Un attaquant sur IP fixe testant le même compte est bloqué après 10 essais.
        // Un credential stuffing distribué (1 essai / IP / compte) est détecté par le limiter IP seul.
        RateLimiter::for('login', function (Request $request) {
            return [
                // Clé 1 : IP + email — bloque le bourrage par dictionnaire ciblé
                // Retourne 429 nativement (pas de ->response() custom)
                Limit::perMinutes(5, 10)
                    ->by('login|email:'.$request->ip().'|'.strtolower((string) $request->input('email', ''))),
                // Clé 2 : IP seule — bloque les robots sur IP fixe testant N comptes différents
                Limit::perMinutes(5, 20)
                    ->by('login|ip:'.$request->ip()),
            ];
        });

        // --- Limiter super-admin login — seuil très bas ---
        // 5 tentatives sur 15 minutes : le backoffice ne doit jamais être bruteforcé.
        RateLimiter::for('super-admin-login', function (Request $request) {
            return Limit::perMinutes(15, 5)
                ->by('sa-login|ip:'.$request->ip());
        });
    }
}
