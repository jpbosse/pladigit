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
        $org  = Organization::where('slug', $slug)
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
            'driver'    => 'mysql',
            'host'      => env('DB_HOST', '127.0.0.1'),
            'port'      => env('DB_PORT', '3306'),
            'database'  => $org->db_name,      // ex: pladigit_mairie_olonne
            'username'  => env('DB_USERNAME'),
            'password'  => env('DB_PASSWORD'),
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]);
 
        DB::purge('tenant');        // Ferme l'ancienne connexion
        DB::reconnect('tenant');    // Ouvre la nouvelle
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
