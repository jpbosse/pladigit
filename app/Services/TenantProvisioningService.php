<?php
 
namespace App\Services;
 
use App\Models\Platform\Organization;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
 
/**
 * Provisionne une nouvelle base de données pour un tenant.
 * Crée la base et exécute toutes les migrations tenant.
 */
class TenantProvisioningService
{
    public function provisionTenant(Organization $org): void
    {
        // 1. Créer la base de données
        DB::statement("CREATE DATABASE IF NOT EXISTS `{$org->db_name}`
            CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
 
        // 2. Activer la connexion tenant vers cette nouvelle base
        app(TenantManager::class)->connectTo($org);
 
        // 3. Exécuter les migrations tenant
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path'     => 'database/migrations/tenant',
            '--force'    => true,
        ]);
 
        // 4. Insérer la ligne tenant_settings par défaut
        DB::connection('tenant')->table('tenant_settings')->insertOrIgnore([
            'updated_at' => now(),
        ]);
 
        $org->update(['status' => 'active']);
    }
}
