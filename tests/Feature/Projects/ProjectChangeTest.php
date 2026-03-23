<?php

namespace Tests\Feature\Projects;

use App\Models\Tenant\Project;
use App\Models\Tenant\ProjectCommAction;
use App\Models\Tenant\ProjectMember;
use App\Models\Tenant\ProjectRisk;
use App\Models\Tenant\User;
use Tests\TestCase;

/**
 * Tests de la conduite du changement : plan de communication + registre des risques.
 * Policy : manageChange = owner ET member (tout contributeur peut identifier un risque).
 * Viewer et non-membre : 403.
 */
class ProjectChangeTest extends TestCase
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

    // ── Plan de communication — store ─────────────────────────────────────────

    public function test_owner_peut_ajouter_une_action_de_communication(): void
    {
        $this->actingAs($this->owner);

        $this->post(route('projects.comm_actions.store', $this->project), [
            'title' => 'Réunion information agents',
            'target_audience' => 'Agents du service',
            'channel' => 'reunion',
            'planned_at' => '2026-04-15',
        ])->assertRedirect();

        $this->assertDatabaseHas('project_comm_actions', [
            'project_id' => $this->project->id,
            'title' => 'Réunion information agents',
            'channel' => 'reunion',
        ], 'tenant');
    }

    public function test_member_peut_ajouter_une_action_de_communication(): void
    {
        $this->actingAs($this->member);

        $this->post(route('projects.comm_actions.store', $this->project), [
            'title' => 'Email de lancement',
            'target_audience' => 'Élus',
            'channel' => 'email',
            'planned_at' => '2026-04-01',
        ])->assertRedirect();

        $this->assertDatabaseHas('project_comm_actions', [
            'project_id' => $this->project->id,
            'title' => 'Email de lancement',
        ], 'tenant');
    }

    public function test_viewer_ne_peut_pas_ajouter_une_action_de_communication(): void
    {
        $this->actingAs($this->viewer);

        $this->post(route('projects.comm_actions.store', $this->project), [
            'title' => 'Tentative viewer',
            'target_audience' => 'Test',
            'channel' => 'email',
            'planned_at' => '2026-04-01',
        ])->assertForbidden();
    }

    // ── Plan de communication — update / destroy ──────────────────────────────

    public function test_owner_peut_marquer_une_action_comme_faite(): void
    {
        $action = ProjectCommAction::on('tenant')->create([
            'project_id' => $this->project->id,
            'title' => 'Action à valider',
            'target_audience' => 'Agents',
            'channel' => 'affichage',
            'planned_at' => now()->subDay(),
        ]);

        $this->actingAs($this->owner);

        $this->patch(route('projects.comm_actions.update', [$this->project, $action]), [
            'done_at' => now()->toDateString(),
        ])->assertRedirect();

        $this->assertDatabaseHas('project_comm_actions', [
            'id' => $action->id,
        ], 'tenant');

        $this->assertNotNull(
            ProjectCommAction::on('tenant')->find($action->id)->done_at
        );
    }

    public function test_member_peut_supprimer_une_action_de_communication(): void
    {
        $action = ProjectCommAction::on('tenant')->create([
            'project_id' => $this->project->id,
            'title' => 'À supprimer par member',
            'target_audience' => 'Test',
            'channel' => 'email',
            'planned_at' => now()->addDays(5),
        ]);

        $this->actingAs($this->member);

        $this->delete(route('projects.comm_actions.destroy', [$this->project, $action]))
            ->assertRedirect();

        $this->assertSoftDeleted('project_comm_actions', ['id' => $action->id], 'tenant');
    }

    // ── Registre des risques — store ──────────────────────────────────────────

    public function test_owner_peut_ajouter_un_risque(): void
    {
        $this->actingAs($this->owner);

        $this->post(route('projects.risks.store', $this->project), [
            'title' => 'Retard fournisseur',
            'category' => 'budget',
            'probability' => 'high',
            'impact' => 'critical',
        ])->assertRedirect();

        $this->assertDatabaseHas('project_risks', [
            'project_id' => $this->project->id,
            'title' => 'Retard fournisseur',
            'probability' => 'high',
            'impact' => 'critical',
        ], 'tenant');
    }

    public function test_member_peut_ajouter_un_risque(): void
    {
        $this->actingAs($this->member);

        $this->post(route('projects.risks.store', $this->project), [
            'title' => 'Risque identifié par member',
            'category' => 'technique',
            'probability' => 'medium',
            'impact' => 'high',
        ])->assertRedirect();

        $this->assertDatabaseHas('project_risks', [
            'project_id' => $this->project->id,
            'title' => 'Risque identifié par member',
        ], 'tenant');
    }

    public function test_viewer_ne_peut_pas_ajouter_un_risque(): void
    {
        $this->actingAs($this->viewer);

        $this->post(route('projects.risks.store', $this->project), [
            'title' => 'Tentative viewer',
            'category' => 'humain',
            'probability' => 'low',
            'impact' => 'low',
        ])->assertForbidden();
    }

    // ── Registre des risques — update / destroy ───────────────────────────────

    public function test_owner_peut_mettre_a_jour_le_plan_de_mitigation(): void
    {
        $risk = ProjectRisk::on('tenant')->create([
            'project_id' => $this->project->id,
            'title' => 'Risque sans plan',
            'category' => 'planning',
            'probability' => 'medium',
            'impact' => 'medium',
            'status' => 'identified',
        ]);

        $this->actingAs($this->owner);

        $this->patch(route('projects.risks.update', [$this->project, $risk]), [
            'mitigation_plan' => 'Prévoir une réserve de 3 semaines.',
            'status' => 'monitored',
        ])->assertRedirect();

        $this->assertDatabaseHas('project_risks', [
            'id' => $risk->id,
            'status' => 'monitored',
        ], 'tenant');
    }

    public function test_member_peut_supprimer_un_risque(): void
    {
        $risk = ProjectRisk::on('tenant')->create([
            'project_id' => $this->project->id,
            'title' => 'Risque à clore',
            'category' => 'humain',
            'probability' => 'low',
            'impact' => 'low',
            'status' => 'identified',
        ]);

        $this->actingAs($this->member);

        $this->delete(route('projects.risks.destroy', [$this->project, $risk]))
            ->assertRedirect();

        $this->assertSoftDeleted('project_risks', ['id' => $risk->id], 'tenant');
    }

    public function test_non_membre_ne_peut_pas_ajouter_un_risque(): void
    {
        $this->actingAs($this->outsider);

        $this->post(route('projects.risks.store', $this->project), [
            'title' => 'Hack',
            'category' => 'autre',
            'probability' => 'low',
            'impact' => 'low',
        ])->assertForbidden();
    }
}
