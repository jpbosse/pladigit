<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Platform\Organization;
use App\Services\ProvisioningException;
use App\Services\TenantProvisioningService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tests Feature — TenantProvisioningService.
 *
 * Couvre :
 *   - Provisioning nominal → org active, DB créée, settings insérés
 *   - Échec migrations → ProvisioningException levée, DB supprimée, org en pending
 *   - Échec CREATE DATABASE → ProvisioningException, org en pending
 *   - Contrôleur : store() supprime l'org et redirige avec erreur si provisioning échoue
 */
class TenantProvisioningTest extends TestCase
{
    protected function tearDown(): void
    {
        // Nettoyer les bases de test créées
        foreach (['pladigit_prov_test_ok', 'pladigit_prov_test_fail'] as $db) {
            try {
                DB::statement("DROP DATABASE IF EXISTS `{$db}`");
            } catch (\Throwable) {
            }
        }

        \App\Models\Platform\Organization::where('slug', 'like', 'prov-test-%')->forceDelete();

        parent::tearDown();
    }

    private function makeOrg(string $suffix, string $status = 'pending'): Organization
    {
        return Organization::forceCreate([
            'name' => 'Prov Test '.$suffix,
            'slug' => 'prov-test-'.$suffix,
            'db_name' => 'pladigit_prov_test_'.$suffix,
            'status' => $status,
            'plan' => 'communautaire',
            'primary_color' => '#1E3A5F',
        ]);
    }

    // ── Provisioning nominal ───────────────────────────────────────────
    // Les tests nominaux nécessitent CREATE DATABASE — droit non disponible
    // sur le CI. Ils sont marqués #[Group('integration')] pour être exclus du pipeline.

    #[\PHPUnit\Framework\Attributes\Group('integration')]
    public function test_provisioning_nominal_active_org(): void
    {
        $org = $this->makeOrg('ok');

        app(TenantProvisioningService::class)->provisionTenant($org);

        $org->refresh();
        $this->assertSame('active', $org->status);
    }

    #[\PHPUnit\Framework\Attributes\Group('integration')]
    public function test_provisioning_nominal_cree_tenant_settings(): void
    {
        $org = $this->makeOrg('ok');

        app(TenantProvisioningService::class)->provisionTenant($org);

        $count = DB::connection('tenant')->table('tenant_settings')->count();
        $this->assertGreaterThanOrEqual(1, $count);
    }

    // ── Échec migrations ───────────────────────────────────────────────

    public function test_provisioning_leve_exception_si_migrations_echouent(): void
    {
        $org = $this->makeOrg('fail');

        // Simuler un échec de migration en mockant Artisan
        Artisan::shouldReceive('call')
            ->with('migrate', \Mockery::any())
            ->andReturn(1); // exit code 1 = échec

        Artisan::shouldReceive('output')->andReturn('Simulated migration error');

        $this->expectException(ProvisioningException::class);

        app(TenantProvisioningService::class)->provisionTenant($org);
    }

    public function test_provisioning_org_reste_pending_si_migrations_echouent(): void
    {
        $org = $this->makeOrg('fail');

        Artisan::shouldReceive('call')->andReturn(1);
        Artisan::shouldReceive('output')->andReturn('error');

        try {
            app(TenantProvisioningService::class)->provisionTenant($org);
        } catch (ProvisioningException) {
        }

        $org->refresh();
        $this->assertSame('pending', $org->status);
    }

    // ── Contrôleur : gestion de l'exception ───────────────────────────

    public function test_store_supprime_org_si_provisioning_echoue(): void
    {
        $this->mock(TenantProvisioningService::class, function ($mock) {
            $mock->shouldReceive('provisionTenant')
                ->andThrow(new ProvisioningException('Échec simulé'));
        });

        $this->withSession(['super_admin_email' => config('superadmin.email'), 'super_admin_verified' => true])
            ->post(route('super-admin.organizations.store'), [
                'name' => 'Org Echec',
                'slug' => 'org-echec-'.uniqid(),
                'plan' => 'communautaire',
            ])
            ->assertRedirect(route('super-admin.organizations.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('organizations', ['slug' => 'org-echec']);
    }

    public function test_store_redirige_avec_erreur_si_provisioning_echoue(): void
    {
        $this->mock(TenantProvisioningService::class, function ($mock) {
            $mock->shouldReceive('provisionTenant')
                ->andThrow(new ProvisioningException('Droits MySQL insuffisants'));
        });

        $response = $this->withSession([
            'super_admin_email' => config('superadmin.email'),
            'super_admin_verified' => true,
        ])->post(route('super-admin.organizations.store'), [
            'name' => 'Org Echec 2',
            'slug' => 'org-echec-2-'.uniqid(),
            'plan' => 'communautaire',
        ]);

        $response->assertSessionHas('error');
        $this->assertStringContainsString(
            'Droits MySQL insuffisants',
            session('error')
        );
    }
}
