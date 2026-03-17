<?php

namespace Tests\Feature\Projects;

use App\Enums\ProjectRole;
use App\Enums\UserRole;
use App\Models\Tenant\Project;
use App\Models\Tenant\ProjectMember;
use App\Models\Tenant\User;
use Tests\TestCase;

/**
 * Tests CRUD des projets.
 *
 * Couvre :
 *   - Création par rôles autorisés et refus des non-autorisés
 *   - Lecture : scope visibleFor (membre vs non-membre vs Admin)
 *   - Modification et suppression par l'owner
 *   - Isolation tenant (softDelete)
 *   - Validations des champs
 */
class ProjectCrudTest extends TestCase
{
    private User $admin;

    private User $respDir;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::ADMIN->value,          'status' => 'active']);
        $this->respDir = User::factory()->create(['role' => UserRole::RESP_DIRECTION->value, 'status' => 'active']);
        $this->user = User::factory()->create(['role' => UserRole::USER->value,           'status' => 'active']);
    }

    // ── Création ──────────────────────────────────────────────────────────

    public function test_admin_peut_creer_un_projet(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('projects.store'), [
            'name' => 'Projet Test',
            'status' => 'active',
            'start_date' => '2026-01-01',
            'due_date' => '2026-12-31',
            'color' => '#1E3A5F',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('projects', [
            'name' => 'Projet Test',
            'created_by' => $this->admin->id,
            'status' => 'active',
        ], 'tenant');

        // Créateur automatiquement ajouté comme owner
        $project = Project::on('tenant')->where('name', 'Projet Test')->first();
        $this->assertDatabaseHas('project_members', [
            'project_id' => $project->id,
            'user_id' => $this->admin->id,
            'role' => ProjectRole::OWNER->value,
        ], 'tenant');
    }

    public function test_resp_direction_peut_creer_un_projet(): void
    {
        $this->actingAs($this->respDir);

        $response = $this->post(route('projects.store'), [
            'name' => 'Projet Direction',
            'status' => 'active',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('projects', ['name' => 'Projet Direction'], 'tenant');
    }

    public function test_utilisateur_simple_ne_peut_pas_creer_un_projet(): void
    {
        $this->actingAs($this->user);

        $response = $this->post(route('projects.store'), [
            'name' => 'Projet Interdit',
            'status' => 'active',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('projects', ['name' => 'Projet Interdit'], 'tenant');
    }

    public function test_creation_echoue_sans_nom(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('projects.store'), ['status' => 'active']);
        $response->assertSessionHasErrors('name');
    }

    public function test_creation_echoue_si_due_date_avant_start_date(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('projects.store'), [
            'name' => 'Mauvaises dates',
            'status' => 'active',
            'start_date' => '2026-12-31',
            'due_date' => '2026-01-01',
        ]);

        $response->assertSessionHasErrors('due_date');
    }

    // ── Lecture ───────────────────────────────────────────────────────────

    public function test_admin_voit_tous_les_projets(): void
    {
        $project = Project::factory()->create(['created_by' => $this->user->id]);

        $this->actingAs($this->admin);
        $response = $this->get(route('projects.index'));
        $response->assertOk()->assertSee($project->name);
    }

    public function test_non_membre_ne_voit_pas_le_projet(): void
    {
        $owner = User::factory()->create(['role' => UserRole::RESP_DIRECTION->value, 'status' => 'active']);
        $project = Project::factory()->create(['created_by' => $owner->id]);
        ProjectMember::create(['project_id' => $project->id, 'user_id' => $owner->id, 'role' => 'owner']);

        $this->actingAs($this->user);
        $response = $this->get(route('projects.index'));
        $response->assertOk()->assertDontSee($project->name);
    }

    // ── Modification ──────────────────────────────────────────────────────

    public function test_owner_peut_modifier_son_projet(): void
    {
        $project = Project::factory()->create(['created_by' => $this->admin->id]);
        ProjectMember::create(['project_id' => $project->id, 'user_id' => $this->admin->id, 'role' => 'owner']);

        $this->actingAs($this->admin);
        $response = $this->put(route('projects.update', $project), [
            'name' => 'Nom modifié',
            'status' => 'on_hold',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('projects', ['id' => $project->id, 'name' => 'Nom modifié', 'status' => 'on_hold'], 'tenant');
    }

    public function test_viewer_ne_peut_pas_modifier_le_projet(): void
    {
        $project = Project::factory()->create(['created_by' => $this->admin->id]);
        ProjectMember::create(['project_id' => $project->id, 'user_id' => $this->admin->id, 'role' => 'owner']);
        ProjectMember::create(['project_id' => $project->id, 'user_id' => $this->user->id,  'role' => 'viewer']);

        $this->actingAs($this->user);
        $response = $this->put(route('projects.update', $project), ['name' => 'Tentative', 'status' => 'active']);
        $response->assertForbidden();
    }

    // ── Suppression ───────────────────────────────────────────────────────

    public function test_owner_peut_supprimer_son_projet(): void
    {
        $project = Project::factory()->create(['created_by' => $this->admin->id]);
        ProjectMember::create(['project_id' => $project->id, 'user_id' => $this->admin->id, 'role' => 'owner']);

        $this->actingAs($this->admin);
        $response = $this->delete(route('projects.destroy', $project));
        $response->assertRedirect(route('projects.index'));

        $this->assertSoftDeleted('projects', ['id' => $project->id], 'tenant');
    }

    public function test_statut_invalide_est_refuse(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('projects.store'), [
            'name' => 'Statut invalide',
            'status' => 'fantome',
        ]);

        $response->assertSessionHasErrors('status');
    }
}
