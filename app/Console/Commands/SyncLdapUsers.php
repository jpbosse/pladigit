<?php

namespace App\Console\Commands;

use App\Models\Platform\Organization;
use App\Services\LdapAuthService;
use App\Services\TenantManager;
use Illuminate\Console\Command;

/**
 * Synchronise les utilisateurs LDAP pour tous les tenants actifs.
 * Planifiée toutes les heures via le scheduler Laravel.
 */
class SyncLdapUsers extends Command
{
    protected $signature = 'pladigit:sync-ldap {--tenant= : Slug du tenant (optionnel)}';

    protected $description = 'Synchronise les utilisateurs depuis les annuaires LDAP/AD';

    public function handle(TenantManager $tenantManager, LdapAuthService $ldap): int
    {
        $slug = $this->option('tenant');

        $query = Organization::where('status', 'active');

        if ($slug) {
            $query->where('slug', $slug);

            if ($query->doesntExist()) {
                $this->error("Tenant introuvable ou inactif : {$slug}");

                return Command::FAILURE;
            }
        }

        $orgs = $query->get();

        foreach ($orgs as $org) {
            try {
                $tenantManager->connectTo($org);
                $ldap->syncAllUsers();
                $this->info("✓ {$org->name} — synchronisé");
            } catch (\Throwable $e) {
                $this->error("✗ {$org->name} — {$e->getMessage()}");
            }
        }

        return Command::SUCCESS;
    }
}
