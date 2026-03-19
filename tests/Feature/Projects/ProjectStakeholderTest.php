<?php

namespace Tests\Feature\Projects;

use App\Models\Tenant\Project;
use App\Models\Tenant\ProjectMember;
use App\Models\Tenant\ProjectStakeholder;
use App\Models\Tenant\User;
use Tests\TestCase;

/**
 * Tests de la gestion des parties prenantes.
 * Policy : manageStakeholders = owner uniquement (+ Admin/DGS via before()).
 */
class ProjectStakeholderTest extends TestCase
{
    private User $owner;

    private User $member;

    private User $viewer;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['role' => 'resp_direction', 'status' => 'active']);
        $this->member = User::factory()->create(['role' => 'user',           'status' => 'active']);
        $this->viewer = User::factory()->create(['role' => 'user',           'status' => 'active']);

        $this->project = Project::factory()->create(['created_by' => $this->owner->id]);
        ProjectMember::create(['project_id' => $this->project->id, 'user_id' => $this->owner->id,  'role' => 'owner']);
        ProjectMember::create(['project_id' => $this->project->id, 'user_id' => $this->member->id, 'role' => 'member']);
        ProjectMember::create(['project_id' => $this->project->id, 'user_id' => $this->viewer->id, 'role' => 'viewer']);
    }

    // ── store ─────────────────────────────────────────────────────────────────

    public function test_owner_peut_ajouter_une_partie_prenante(): void
    {
        $this->actingAs($this->owner);

        $this->post(route('projects.stakeholders.store', $this->project), [
            'name' => 'Conseil municipal',
            'role' => 'Décideur',
            'adhesion' => 'neutre',
            'influence' => 'high',
        ])->assertRedirect();

        $this->assertDatabaseHas('project_stakeholders', [
            'project_id' => $this->project->id,
            'name' => 'Conseil municipal',
            'adhesion' => 'neutre',
        ], 'tenant');
    }

    public function test_member_ne_peut_pas_ajouter_une_partie_prenante(): void
    {
        $this->actingAs($this->member);

        $this->post(route('projects.stakeholders.store', $this->project), [
            'name' => 'Tentative',
            'role' => 'Test',
            'adhesion' => 'champion',
            'influence' => 'low',
        ])->assertForbidden();
    }

    public function test_owner_peut_associer_un_utilisateur_interne(): void
    {
        $internalUser = User::factory()->create(['status' => 'active']);

        $this->actingAs($this->owner);

        $this->post(route('projects.stakeholders.store', $this->project), [
            'user_id' => $internalUser->id,
            'role' => 'Référent technique',
            'adhesion' => 'supporter',
            'influence' => 'medium',
        ])->assertRedirect();

        $this->assertDatabaseHas('project_stakeholders', [
            'project_id' => $this->project->id,
            'user_id' => $internalUser->id,
            'adhesion' => 'supporter',
        ], 'tenant');
    }

    // ── update ────────────────────────────────────────────────────────────────

    public function test_owner_peut_modifier_ladhesion(): void
    {
        $sh = ProjectStakeholder::on('tenant')->create([
            'project_id' => $this->project->id,
            'name' => 'Association locale',
            'role' => 'Partenaire',
            'adhesion' => 'vigilant',
            'influence' => 'medium',
        ]);

        $this->actingAs($this->owner);

        $this->patch(route('projects.stakeholders.update', [$this->project, $sh]), [
            'adhesion' => 'champion',
        ])->assertRedirect();

        $this->assertDatabaseHas('project_stakeholders', [
            'id' => $sh->id,
            'adhesion' => 'champion',
        ], 'tenant');
    }

    public function test_viewer_ne_peut_pas_modifier_une_partie_prenante(): void
    {
        $sh = ProjectStakeholder::on('tenant')->create([
            'project_id' => $this->project->id,
            'name' => 'Partie protégée',
            'role' => 'Observateur',
            'adhesion' => 'neutre',
            'influence' => 'low',
        ]);

        $this->actingAs($this->viewer);

        $this->patch(route('projects.stakeholders.update', [$this->project, $sh]), [
            'adhesion' => 'resistant',
        ])->assertForbidden();
    }

    // ── destroy ───────────────────────────────────────────────────────────────

    public function test_owner_peut_supprimer_une_partie_prenante(): void
    {
        $sh = ProjectStakeholder::on('tenant')->create([
            'project_id' => $this->project->id,
            'name' => 'À retirer',
            'role' => 'Ex-partenaire',
            'adhesion' => 'resistant',
            'influence' => 'low',
        ]);

        $this->actingAs($this->owner);

        $this->delete(route('projects.stakeholders.destroy', [$this->project, $sh]))->assertRedirect();

        $this->assertSoftDeleted('project_stakeholders', ['id' => $sh->id], 'tenant');
    }
}
