<?php

namespace App\Services;

use App\Models\Platform\Organization;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Provisionne une nouvelle base de données pour un tenant.
 *
 * Séquence :
 *   1. Créer la base MySQL dédiée
 *   2. Connecter TenantManager sur cette base
 *   3. Exécuter les migrations tenant
 *   4. Insérer tenant_settings par défaut
 *   5. Passer l'organisation en statut 'active'
 *
 * En cas d'échec à n'importe quelle étape :
 *   - La base MySQL créée est supprimée (DROP DATABASE)
 *   - L'organisation reste en statut 'pending'
 *   - Une ProvisioningException est levée avec le contexte complet
 *
 * Note : CREATE/DROP DATABASE sont des DDL MySQL — non rollbackables
 * via DB::transaction(). La compensation est donc manuelle.
 */
class TenantProvisioningService
{
    /**
     * @throws ProvisioningException Si le provisioning échoue à n'importe quelle étape
     */
    public function provisionTenant(Organization $org): void
    {
        $dbCreated = false;

        try {
            // 1. Créer la base de données
            DB::statement("CREATE DATABASE IF NOT EXISTS `{$org->db_name}`
                CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $dbCreated = true;

            // 2. Activer la connexion tenant vers cette nouvelle base
            app(TenantManager::class)->connectTo($org);

            // 3. Exécuter les migrations tenant
            $exitCode = Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => 'database/migrations/tenant',
                '--force' => true,
            ]);

            if ($exitCode !== 0) {
                throw new \RuntimeException(
                    'Les migrations tenant ont échoué (exit code '.$exitCode.'). '.
                    'Sortie Artisan : '.Artisan::output()
                );
            }

            // 4. Insérer la ligne tenant_settings par défaut
            DB::connection('tenant')->table('tenant_settings')->insertOrIgnore([
                'updated_at' => now(),
            ]);

            // 5. Activer l'organisation
            $org->update(['status' => 'active']);

        } catch (\Throwable $e) {
            // Compensation : supprimer la base si elle a été créée
            if ($dbCreated) {
                try {
                    DB::statement("DROP DATABASE IF EXISTS `{$org->db_name}`");
                } catch (\Throwable $dropException) {
                    Log::error('TenantProvisioningService : impossible de supprimer la base après échec', [
                        'org' => $org->slug,
                        'db_name' => $org->db_name,
                        'drop_error' => $dropException->getMessage(),
                    ]);
                }
            }

            // L'org reste en 'pending' — pas d'update status
            Log::error('TenantProvisioningService : échec du provisioning', [
                'org' => $org->slug,
                'db_name' => $org->db_name,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new ProvisioningException(
                "Échec du provisioning pour « {$org->name} » : ".$e->getMessage(),
                previous: $e
            );
        }
    }
}
