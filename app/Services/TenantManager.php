<?php

namespace App\Services;

use App\Models\Platform\Organization;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * TenantManager — résolution et connexion au tenant courant.
 * Chaque organisation dispose d'une base MySQL dédiée.
 */
class TenantManager
{
    private ?Organization $currentTenant = null;

    /**
     * Résout et active le tenant depuis le sous-domaine de la requête.
     * Ex : mairie-olonne.pladigit.fr → slug = mairie-olonne
     */
    public function resolveFromRequest(string $host): void
    {
        $slug = explode('.', $host)[0];
        $org = Organization::where('slug', $slug)
            ->where('status', 'active')
            ->firstOrFail();
        $this->connectTo($org);
    }

    /**
     * Configure la connexion Eloquent 'tenant' vers la base de l'organisation.
     */
    public function connectTo(Organization $org): void
    {
        $this->currentTenant = $org;

        Config::set('database.connections.tenant', [
            'driver' => 'mysql',
            'host' => config('database.connections.mysql.host'),
            'port' => config('database.connections.mysql.port'),
            'database' => $org->db_name,
            'username' => config('database.connections.mysql.username'),
            'password' => config('database.connections.mysql.password'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]);

        DB::purge('tenant');
        DB::reconnect('tenant');
    }

    public function current(): ?Organization
    {
        return $this->currentTenant;
    }

    public function currentOrFail(): Organization
    {
        return $this->currentTenant ?? throw new \RuntimeException('Aucun tenant résolu.');
    }

    /** Retourne true si un tenant est actif dans la requête courante. */
    public function hasTenant(): bool
    {
        return $this->currentTenant !== null;
    }
}
