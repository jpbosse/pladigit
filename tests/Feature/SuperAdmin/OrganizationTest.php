<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Platform\Organization;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Tests Feature — Gestion des organisations (Super Admin).
 */
class OrganizationTest extends TestCase
{
use DatabaseTransactions;
protected $connectionsToTransact = ['mysql'];

protected function setUpPlatformDatabase(): void
{
    $this->artisan('migrate', [
        '--database' => 'mysql',
        '--path'     => 'database/migrations/platform',
        '--force'    => true,
    ]);
}

    protected $connectionsToTransact = ['mysql'];
    // ── Helpers ────────────────────────────────────────────────────────

    private function actingAsSuperAdmin()
    {
        return $this->withSession([
            'super_admin_email' => config('superadmin.email'),
            'super_admin_verified' => true,
        ]);
    }

    // ── Accès ──────────────────────────────────────────────────────────

    public function test_invité_ne_peut_pas_accéder_au_super_admin(): void
    {
        $this->get(route('super-admin.organizations.index'))
            ->assertRedirect();
    }

    public function test_super_admin_peut_voir_la_liste(): void
    {
        $this->actingAsSuperAdmin()
            ->get(route('super-admin.organizations.index'))
            ->assertOk()
            ->assertViewIs('super-admin.organizations.index');
    }

    // ── Création ───────────────────────────────────────────────────────

    public function test_super_admin_peut_créer_une_organisation(): void
    {
        $this->actingAsSuperAdmin()
            ->post(route('super-admin.organizations.store'), [
                'name' => 'Commune de Test',
                'slug' => 'commune-test',
                'plan' => 'communautaire',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('organizations', [
            'name' => 'Commune de Test',
            'slug' => 'commune-test',
            'plan' => 'communautaire',
        ]);
    }

    public function test_création_organisation_slug_dupliqué_refusée(): void
    {
        Organization::factory()->create(['slug' => 'commune-test']);

        $this->actingAsSuperAdmin()
            ->post(route('super-admin.organizations.store'), [
                'name' => 'Autre Commune',
                'slug' => 'commune-test',
                'plan' => 'communautaire',
            ])
            ->assertSessionHasErrors('slug');
    }

    public function test_création_organisation_sans_nom_refusée(): void
    {
        $this->actingAsSuperAdmin()
            ->post(route('super-admin.organizations.store'), [
                'name' => '',
                'slug' => 'commune-test',
                'plan' => 'communautaire',
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_création_organisation_plan_invalide_refusée(): void
    {
        $this->actingAsSuperAdmin()
            ->post(route('super-admin.organizations.store'), [
                'name' => 'Commune Test',
                'slug' => 'commune-test',
                'plan' => 'gratuit',
            ])
            ->assertSessionHasErrors('plan');
    }

    public function test_plan_communautaire_donne_max_users_9999(): void
    {
        $this->actingAsSuperAdmin()
            ->post(route('super-admin.organizations.store'), [
                'name' => 'Commune Test',
                'slug' => 'commune-test',
                'plan' => 'communautaire',
            ]);

        $this->assertDatabaseHas('organizations', [
            'slug' => 'commune-test',
            'max_users' => 9999,
        ]);
    }

    public function test_plan_assistance_donne_max_users_200(): void
    {
        $this->actingAsSuperAdmin()
            ->post(route('super-admin.organizations.store'), [
                'name' => 'Commune Assistance',
                'slug' => 'commune-assistance',
                'plan' => 'assistance',
            ]);

        $this->assertDatabaseHas('organizations', [
            'slug' => 'commune-assistance',
            'max_users' => 200,
        ]);
    }

    // ── Modification ───────────────────────────────────────────────────

    public function test_super_admin_peut_modifier_une_organisation(): void
    {
        $org = Organization::factory()->create(['name' => 'Ancien nom', 'plan' => 'communautaire']);

        $this->actingAsSuperAdmin()
            ->put(route('super-admin.organizations.update', $org), [
                'name' => 'Nouveau nom',
                'plan' => 'assistance',
                'status' => 'active',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('organizations', [
            'id' => $org->id,
            'name' => 'Nouveau nom',
            'plan' => 'assistance',
        ]);
    }

    public function test_modification_statut_invalide_refusée(): void
    {
        $org = Organization::factory()->create();

        $this->actingAsSuperAdmin()
            ->put(route('super-admin.organizations.update', $org), [
                'name' => $org->name,
                'plan' => $org->plan,
                'status' => 'banni',
            ])
            ->assertSessionHasErrors('status');
    }

    // ── Suspension / Activation ────────────────────────────────────────

    public function test_super_admin_peut_suspendre_une_organisation(): void
    {
        $org = Organization::factory()->create(['status' => 'active']);

        $this->actingAsSuperAdmin()
            ->post(route('super-admin.organizations.suspend', $org))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('organizations', ['id' => $org->id, 'status' => 'suspended']);
    }

    public function test_super_admin_peut_réactiver_une_organisation(): void
    {
        $org = Organization::factory()->create(['status' => 'suspended']);

        $this->actingAsSuperAdmin()
            ->post(route('super-admin.organizations.activate', $org))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('organizations', ['id' => $org->id, 'status' => 'active']);
    }
}
