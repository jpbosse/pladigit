<?php

namespace Tests\Feature\Admin;

use App\Models\Tenant\Department;
use App\Models\Tenant\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tests Feature — Gestion des directions et services.
 */
class DepartmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
    }

    protected function tearDown(): void
    {
        DB::connection('tenant')->table('user_department')->delete();
        DB::connection('tenant')->statement('DELETE FROM departments');
        parent::tearDown();
    }

    // ── Helpers ────────────────────────────────────────────────────────

    private function assertTenantHas(string $table, array $data): void
    {
        $this->assertDatabaseHas($table, $data, 'tenant');
    }

    private function assertTenantCount(string $table, int $count): void
    {
        $this->assertDatabaseCount($table, $count, 'tenant');
    }

    private function assertTenantSoftDeleted(string $table, array $data): void
    {
        $this->assertSoftDeleted($table, $data, 'tenant');
    }

    // ── Accès ──────────────────────────────────────────────────────────

    public function test_invité_ne_peut_pas_accéder_aux_départements(): void
    {
        $this->get(route('admin.departments.index'))
            ->assertRedirect(route('login'));
    }

    public function test_utilisateur_non_admin_ne_peut_pas_accéder(): void
    {
        $user = User::factory()->create(['role' => 'user', 'status' => 'active']);

        $this->actingAs($user)
            ->get(route('admin.departments.index'))
            ->assertStatus(403);
    }

    public function test_admin_peut_voir_la_liste(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.departments.index'))
            ->assertOk()
            ->assertViewIs('admin.departments.index')
            ->assertViewHas('roots');
    }

    // ── Création direction ─────────────────────────────────────────────

    public function test_admin_peut_créer_une_direction(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.departments.store'), [
                'name' => 'Direction des Services Techniques',
                'type' => 'direction',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertTenantHas('departments', [
            'name' => 'Direction des Services Techniques',
            'type' => 'direction',
            'parent_id' => null,
        ]);
    }

    public function test_création_direction_sans_nom_refusée(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.departments.store'), ['name' => '', 'type' => 'direction'])
            ->assertSessionHasErrors('name');

        $this->assertTenantCount('departments', 0);
    }

    public function test_création_direction_nom_trop_long_refusée(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.departments.store'), [
                'name' => str_repeat('A', 256),
                'type' => 'direction',
            ])
            ->assertSessionHasErrors('name');
    }

    // ── Création service ───────────────────────────────────────────────

    public function test_admin_peut_créer_un_service(): void
    {
        $direction = Department::factory()->direction()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.departments.store'), [
                'name' => 'Service Voirie',
                'type' => 'service',
                'parent_id' => $direction->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertTenantHas('departments', [
            'name' => 'Service Voirie',
            'type' => 'service',
            'parent_id' => $direction->id,
        ]);
    }

    public function test_création_service_sans_parent_devient_direction(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.departments.store'), [
                'name' => 'Service Voirie',
                'type' => 'service',
                'parent_id' => null,
            ])
            ->assertRedirect();

        // Sans parent, le type est dérivé en 'direction'
        $this->assertTenantCount('departments', 1);

        $this->assertDatabaseHas('departments', [
            'name' => 'Service Voirie',
            'type' => 'direction',
        ], 'tenant');
    }

    public function test_création_service_parent_inexistant_refusée(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.departments.store'), [
                'name' => 'Service Voirie',
                'type' => 'service',
                'parent_id' => 9999,
            ])
            ->assertSessionHasErrors('parent_id');
    }

    // ── Renommage ──────────────────────────────────────────────────────

    public function test_admin_peut_renommer_une_direction(): void
    {
        $direction = Department::factory()->direction()->create(['name' => 'Ancien nom']);

        $this->actingAs($this->admin)
            ->put(route('admin.departments.update', $direction), ['name' => 'Nouveau nom'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertTenantHas('departments', ['id' => $direction->id, 'name' => 'Nouveau nom']);
    }

    public function test_admin_peut_renommer_un_service(): void
    {
        $direction = Department::factory()->direction()->create();
        $service = Department::factory()->service($direction->id)->create(['name' => 'Ancien']);

        $this->actingAs($this->admin)
            ->put(route('admin.departments.update', $service), [
                'name' => 'Nouveau',
                'parent_id' => $direction->id,
            ])
            ->assertRedirect();

        $this->assertTenantHas('departments', ['id' => $service->id, 'name' => 'Nouveau']);
    }

    public function test_renommage_nom_vide_refusé(): void
    {
        $direction = Department::factory()->direction()->create(['name' => 'DST']);

        $this->actingAs($this->admin)
            ->put(route('admin.departments.update', $direction), ['name' => ''])
            ->assertSessionHasErrors('name');

        $this->assertTenantHas('departments', ['name' => 'DST']);
    }

    // ── Suppression ────────────────────────────────────────────────────

    public function test_admin_peut_supprimer_direction_vide(): void
    {
        $direction = Department::factory()->direction()->create();

        $this->actingAs($this->admin)
            ->delete(route('admin.departments.destroy', $direction))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertTenantSoftDeleted('departments', ['id' => $direction->id]);
    }

    public function test_admin_peut_supprimer_service_vide(): void
    {
        $direction = Department::factory()->direction()->create();
        $service = Department::factory()->service($direction->id)->create();

        $this->actingAs($this->admin)
            ->delete(route('admin.departments.destroy', $service))
            ->assertRedirect();

        $this->assertTenantSoftDeleted('departments', ['id' => $service->id]);
    }

    public function test_suppression_direction_avec_services_refusée(): void
    {
        $direction = Department::factory()->direction()->create();
        Department::factory()->service($direction->id)->create();

        $this->actingAs($this->admin)
            ->delete(route('admin.departments.destroy', $direction))
            ->assertRedirect()
            ->assertSessionHasErrors('delete');

        $this->assertTenantHas('departments', ['id' => $direction->id, 'deleted_at' => null]);
    }

    public function test_suppression_département_avec_membres_refusée(): void
    {
        $direction = Department::factory()->direction()->create();
        $member = User::factory()->create(['role' => 'user', 'status' => 'active']);
        $direction->members()->attach($member->id, ['is_manager' => false]);

        $this->actingAs($this->admin)
            ->delete(route('admin.departments.destroy', $direction))
            ->assertRedirect()
            ->assertSessionHasErrors('delete');

        $this->assertTenantHas('departments', ['id' => $direction->id, 'deleted_at' => null]);
    }
}
