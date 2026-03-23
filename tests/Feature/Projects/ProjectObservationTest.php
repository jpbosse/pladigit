<?php

namespace Tests\Feature\Projects;

use App\Models\Tenant\Project;
use App\Models\Tenant\ProjectMember;
use App\Models\Tenant\ProjectObservation;
use App\Models\Tenant\User;
use Tests\TestCase;

/**
 * Tests des observations/décisions élus sur un projet.
 * Policy store : tout membre (view suffisant).
 * Policy destroy : auteur ou admin/président/dgs.
 */
class ProjectObservationTest extends TestCase
{
    private User $owner;

    private User $member;

    private User $viewer;

    private User $outsider;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['role' => 'resp_direction', 'status' => 'active']);
        $this->member = User::factory()->create(['role' => 'user',           'status' => 'active']);
        $this->viewer = User::factory()->create(['role' => 'user',           'status' => 'active']);
        $this->outsider = User::factory()->create(['role' => 'user',           'status' => 'active']);

        $this->project = Project::factory()->create(['created_by' => $this->owner->id]);
        ProjectMember::create(['project_id' => $this->project->id, 'user_id' => $this->owner->id,  'role' => 'owner']);
        ProjectMember::create(['project_id' => $this->project->id, 'user_id' => $this->member->id, 'role' => 'member']);
        ProjectMember::create(['project_id' => $this->project->id, 'user_id' => $this->viewer->id, 'role' => 'viewer']);
    }

    // ── store ─────────────────────────────────────────────────────────────────

    public function test_owner_peut_publier_une_observation(): void
    {
        $this->actingAs($this->owner);

        $this->post(route('projects.observations.store', $this->project), [
            'body' => 'Le planning semble trop optimiste pour la phase 2.',
            'type' => 'observation',
        ])->assertRedirect();

        $this->assertDatabaseHas('project_observations', [
            'project_id' => $this->project->id,
            'user_id' => $this->owner->id,
            'type' => 'observation',
        ], 'tenant');
    }

    public function test_member_peut_publier_une_question(): void
    {
        $this->actingAs($this->member);

        $this->post(route('projects.observations.store', $this->project), [
            'body' => 'Quel est le montant réservé pour la communication externe ?',
            'type' => 'question',
        ])->assertRedirect();

        $this->assertDatabaseHas('project_observations', [
            'project_id' => $this->project->id,
            'user_id' => $this->member->id,
            'type' => 'question',
        ], 'tenant');
    }

    public function test_viewer_peut_publier_une_validation(): void
    {
        // Viewer = membre avec rôle viewer — il a le droit view → peut écrire
        $this->actingAs($this->viewer);

        $this->post(route('projects.observations.store', $this->project), [
            'body' => 'Je valide les orientations présentées.',
            'type' => 'validation',
        ])->assertRedirect();

        $this->assertDatabaseHas('project_observations', [
            'project_id' => $this->project->id,
            'user_id' => $this->viewer->id,
            'type' => 'validation',
        ], 'tenant');
    }

    public function test_non_membre_ne_peut_pas_publier(): void
    {
        $this->actingAs($this->outsider);

        $this->post(route('projects.observations.store', $this->project), [
            'body' => 'Tentative intrusion',
            'type' => 'observation',
        ])->assertForbidden();
    }

    public function test_type_invalide_est_rejete(): void
    {
        $this->actingAs($this->owner);

        $this->post(route('projects.observations.store', $this->project), [
            'body' => 'Test type invalide',
            'type' => 'inconnu',
        ])->assertSessionHasErrors('type');
    }

    // ── destroy ───────────────────────────────────────────────────────────────

    public function test_auteur_peut_supprimer_sa_propre_observation(): void
    {
        $obs = ProjectObservation::on('tenant')->create([
            'project_id' => $this->project->id,
            'user_id' => $this->member->id,
            'body' => 'Obs à supprimer',
            'type' => 'observation',
        ]);

        $this->actingAs($this->member);

        $this->delete(route('projects.observations.destroy', [$this->project, $obs]))
            ->assertRedirect();

        $this->assertSoftDeleted('project_observations', ['id' => $obs->id], 'tenant');
    }

    public function test_admin_peut_supprimer_nimporte_quelle_observation(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $obs = ProjectObservation::on('tenant')->create([
            'project_id' => $this->project->id,
            'user_id' => $this->member->id,
            'body' => 'Obs à modérer par admin',
            'type' => 'alerte',
        ]);

        $this->actingAs($admin);

        $this->delete(route('projects.observations.destroy', [$this->project, $obs]))
            ->assertRedirect();

        $this->assertSoftDeleted('project_observations', ['id' => $obs->id], 'tenant');
    }

    public function test_autre_membre_ne_peut_pas_supprimer_lobservation_dautrui(): void
    {
        $obs = ProjectObservation::on('tenant')->create([
            'project_id' => $this->project->id,
            'user_id' => $this->owner->id,
            'body' => 'Obs de l\'owner',
            'type' => 'observation',
        ]);

        $this->actingAs($this->member);

        $this->delete(route('projects.observations.destroy', [$this->project, $obs]))
            ->assertForbidden();
    }
}
