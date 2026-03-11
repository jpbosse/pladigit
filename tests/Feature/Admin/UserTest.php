<?php

namespace Tests\Feature\Admin;

use App\Models\Tenant\Department;
use App\Models\Tenant\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Tests Feature — Gestion des utilisateurs.
 */
class UserTest extends TestCase
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
        DB::connection('tenant')->statement('DELETE FROM users');
        parent::tearDown();
    }

    private function assertTenantHas(string $table, array $data): void
    {
        $this->assertDatabaseHas($table, $data, 'tenant');
    }

    private function assertTenantMissing(string $table, array $data): void
    {
        $this->assertDatabaseMissing($table, $data, 'tenant');
    }

    // ── Accès ──────────────────────────────────────────────────────────

    public function test_invité_ne_peut_pas_accéder_à_la_liste(): void
    {
        $this->get(route('admin.users.index'))
            ->assertRedirect(route('login'));
    }

    public function test_utilisateur_simple_ne_peut_pas_accéder_à_la_liste(): void
    {
        $user = User::factory()->create(['role' => 'user', 'status' => 'active']);

        $this->actingAs($user)
            ->get(route('admin.users.index'))
            ->assertStatus(403);
    }

    public function test_admin_peut_voir_la_liste(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertViewIs('admin.users.index');
    }

    // ── Création ───────────────────────────────────────────────────────

    public function test_admin_peut_créer_un_utilisateur(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.users.store'), [
                'name' => 'Jean Dupont',
                'email' => 'jean.dupont@test.fr',
                'role' => 'user',
                'password' => 'MotDePasse!123',
            ])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success');

        $this->assertTenantHas('users', [
            'email' => 'jean.dupont@test.fr',
            'role' => 'user',
        ]);
    }

    public function test_création_utilisateur_email_dupliqué_refusée(): void
    {
        User::factory()->create(['email' => 'jean.dupont@test.fr']);

        $this->actingAs($this->admin)
            ->post(route('admin.users.store'), [
                'name' => 'Jean Dupont 2',
                'email' => 'jean.dupont@test.fr',
                'role' => 'user',
                'password' => 'MotDePasse!123',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_création_utilisateur_sans_nom_refusée(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.users.store'), [
                'name' => '',
                'email' => 'test@test.fr',
                'role' => 'user',
                'password' => 'MotDePasse!123',
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_création_utilisateur_rôle_invalide_refusée(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.users.store'), [
                'name' => 'Jean Dupont',
                'email' => 'jean@test.fr',
                'role' => 'superpower',
                'password' => 'MotDePasse!123',
            ])
            ->assertSessionHasErrors('role');
    }

    public function test_nouvel_utilisateur_doit_changer_son_mot_de_passe(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.users.store'), [
                'name' => 'Jean Dupont',
                'email' => 'jean.dupont@test.fr',
                'role' => 'user',
                'password' => 'MotDePasse!123',
            ]);

        $this->assertTenantHas('users', [
            'email' => 'jean.dupont@test.fr',
            'force_pwd_change' => 1,
        ]);
    }

    // ── Modification ───────────────────────────────────────────────────

    public function test_admin_peut_modifier_un_utilisateur(): void
    {
        $user = User::factory()->create(['name' => 'Ancien Nom', 'role' => 'user']);

        $this->actingAs($this->admin)
            ->put(route('admin.users.update', $user), [
                'name' => 'Nouveau Nom',
                'role' => 'user',
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success');

        $this->assertTenantHas('users', ['id' => $user->id, 'name' => 'Nouveau Nom']);
    }

    public function test_admin_peut_suspendre_un_utilisateur(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($this->admin)
            ->put(route('admin.users.update', $user), [
                'name' => $user->name,
                'role' => $user->role,
                'status' => 'inactive',
            ])
            ->assertRedirect();

        $this->assertTenantHas('users', ['id' => $user->id, 'status' => 'inactive']);
    }

    public function test_modification_statut_invalide_refusée(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin)
            ->put(route('admin.users.update', $user), [
                'name' => $user->name,
                'role' => $user->role,
                'status' => 'banni',
            ])
            ->assertSessionHasErrors('status');
    }

    // ── Désactivation ──────────────────────────────────────────────────

    public function test_admin_peut_désactiver_un_utilisateur(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($this->admin)
            ->delete(route('admin.users.destroy', $user))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertTenantHas('users', ['id' => $user->id, 'status' => 'inactive']);
    }

    public function test_admin_ne_peut_pas_se_désactiver_lui_même(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('admin.users.destroy', $this->admin))
            ->assertRedirect()
            ->assertSessionHasErrors('error');

        $this->assertTenantHas('users', ['id' => $this->admin->id, 'status' => 'active']);
    }

    // ── Reset mot de passe ─────────────────────────────────────────────

    public function test_admin_peut_réinitialiser_le_mot_de_passe(): void
    {
        $user = User::factory()->create([
            'password_hash' => Hash::make('AncienMotDePasse!1'),
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.users.reset-password', $user))
            ->assertRedirect()
            ->assertSessionHas('success');

        $user->refresh();
        $this->assertTrue((bool) $user->force_pwd_change);
    }

    // ── Affectation départements ───────────────────────────────────────

    public function test_admin_peut_affecter_un_département_à_un_utilisateur(): void
    {
        $direction = Department::factory()->direction()->create();
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($this->admin)
            ->put(route('admin.users.update', $user), [
                'name' => $user->name,
                'role' => 'user',
                'status' => 'active',
                'department_ids' => [$direction->id],
            ])
            ->assertRedirect();

        $this->assertTenantHas('user_department', [
            'user_id' => $user->id,
            'department_id' => $direction->id,
        ]);
    }
}
