<?php

namespace Tests\Feature\Console;

use App\Models\Platform\Organization;
use App\Services\LdapAuthService;
use App\Services\TenantManager;
use Tests\TestCase;

/**
 * SyncLdapUsersTest — Tests de la commande pladigit:sync-ldap (§6.6)
 */
#[\PHPUnit\Framework\Attributes\Group('console')]
class SyncLdapUsersTest extends TestCase
{
    private Organization $orgA;

    private Organization $orgB;

    protected function setUp(): void
    {
        parent::setUp();

        // db_name distincts (contrainte UNIQUE) — valeurs fictives car TenantManager est mocké
        $this->orgA = Organization::forceCreate([
            'slug' => 'org-a',
            'name' => 'Organisation A',
            'db_name' => 'pladigit_test_org_a',
            'status' => 'active',
        ]);

        $this->orgB = Organization::forceCreate([
            'slug' => 'org-b',
            'name' => 'Organisation B',
            'db_name' => 'pladigit_test_org_b',
            'status' => 'active',
        ]);
    }

    protected function tearDown(): void
    {
        Organization::whereIn('slug', ['org-a', 'org-b', 'org-inactive'])->forceDelete();
        parent::tearDown();
    }

    /** Construit un mock TenantManager qui n'essaie pas de se reconnecter en base. */
    private function mockTenantManager(): TenantManager
    {
        $tm = $this->createMock(TenantManager::class);

        $this->app->instance(TenantManager::class, $tm);

        return $tm;
    }

    // ────────────────────────────────────────────────────────────────
    // Sans --tenant : tous les tenants actifs sont synchronisés
    // ────────────────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function sans_option_tenant_synchronise_tous_les_tenants_actifs(): void
    {
        $this->mockTenantManager();

        $ldap = $this->createMock(LdapAuthService::class);
        $ldap->expects($this->exactly(3))->method('syncAllUsers'); // orgA + orgB + org test
        $this->app->instance(LdapAuthService::class, $ldap);

        $this->artisan('pladigit:sync-ldap')
            ->expectsOutputToContain('Organisation A')
            ->expectsOutputToContain('Organisation B')
            ->assertExitCode(0);
    }

    // ────────────────────────────────────────────────────────────────
    // Avec --tenant=slug : seul ce tenant est synchronisé
    // ────────────────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function avec_option_tenant_synchronise_uniquement_ce_tenant(): void
    {
        $this->mockTenantManager();

        $ldap = $this->createMock(LdapAuthService::class);
        $ldap->expects($this->once())->method('syncAllUsers');
        $this->app->instance(LdapAuthService::class, $ldap);

        $this->artisan('pladigit:sync-ldap', ['--tenant' => 'org-a'])
            ->expectsOutputToContain('Organisation A')
            ->assertExitCode(0);
    }

    // ────────────────────────────────────────────────────────────────
    // --tenant slug inconnu → erreur + exit code 1
    // ────────────────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function tenant_inconnu_retourne_failure(): void
    {
        $this->mockTenantManager();

        $ldap = $this->createMock(LdapAuthService::class);
        $ldap->expects($this->never())->method('syncAllUsers');
        $this->app->instance(LdapAuthService::class, $ldap);

        $this->artisan('pladigit:sync-ldap', ['--tenant' => 'slug-inexistant'])
            ->expectsOutputToContain('slug-inexistant')
            ->assertExitCode(1);
    }

    // ────────────────────────────────────────────────────────────────
    // Tenant inactif ignoré quand pas d'option
    // ────────────────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function tenant_inactif_ignore_sans_option(): void
    {
        Organization::forceCreate([
            'slug' => 'org-inactive',
            'name' => 'Organisation Inactive',
            'db_name' => 'pladigit_test_org_inactive',
            'status' => 'suspended',
        ]);

        $this->mockTenantManager();

        $ldap = $this->createMock(LdapAuthService::class);
        $ldap->expects($this->exactly(3))->method('syncAllUsers'); // orgA + orgB + org test (sans org-inactive)
        $this->app->instance(LdapAuthService::class, $ldap);

        $this->artisan('pladigit:sync-ldap')->assertExitCode(0);
    }

    // ────────────────────────────────────────────────────────────────
    // --tenant sur un tenant inactif → erreur
    // ────────────────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function tenant_inactif_cible_retourne_failure(): void
    {
        Organization::forceCreate([
            'slug' => 'org-inactive',
            'name' => 'Organisation Inactive',
            'db_name' => 'pladigit_test_org_inactive',
            'status' => 'suspended',
        ]);

        $this->mockTenantManager();

        $ldap = $this->createMock(LdapAuthService::class);
        $ldap->expects($this->never())->method('syncAllUsers');
        $this->app->instance(LdapAuthService::class, $ldap);

        $this->artisan('pladigit:sync-ldap', ['--tenant' => 'org-inactive'])
            ->expectsOutputToContain('org-inactive')
            ->assertExitCode(1);
    }
}
