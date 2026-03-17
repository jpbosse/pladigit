<?php

namespace Tests\Feature\Projects;

use App\Models\Tenant\Project;
use App\Models\Tenant\ProjectMember;
use App\Models\Tenant\ProjectMilestone;
use App\Models\Tenant\User;
use Tests\TestCase;

/**
 * Tests des jalons de projet.
 */
class ProjectMilestoneTest extends TestCase
{
    private User $owner;

    private User $member;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->member = User::factory()->create(['role' => 'user',  'status' => 'active']);

        $this->project = Project::factory()->create(['created_by' => $this->owner->id]);
        ProjectMember::create(['project_id' => $this->project->id, 'user_id' => $this->owner->id,  'role' => 'owner']);
        ProjectMember::create(['project_id' => $this->project->id, 'user_id' => $this->member->id, 'role' => 'member']);
    }

    public function test_owner_peut_creer_un_jalon(): void
    {
        $this->actingAs($this->owner);

        $response = $this->post(route('projects.milestones.store', $this->project), [
            'title' => 'Livraison Phase 3',
            'due_date' => '2026-06-30',
            'color' => '#EA580C',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('project_milestones', [
            'project_id' => $this->project->id,
            'title' => 'Livraison Phase 3',
        ], 'tenant');
    }

    public function test_member_ne_peut_pas_creer_un_jalon(): void
    {
        $this->actingAs($this->member);
        $response = $this->post(route('projects.milestones.store', $this->project), [
            'title' => 'Jalon interdit',
            'due_date' => '2026-06-30',
        ]);
        $response->assertForbidden();
    }

    public function test_owner_peut_marquer_un_jalon_comme_atteint(): void
    {
        $milestone = ProjectMilestone::create([
            'project_id' => $this->project->id,
            'title' => 'Jalon test',
            'due_date' => '2026-06-30',
            'color' => '#EA580C',
        ]);

        $this->actingAs($this->owner);
        $response = $this->patch(route('projects.milestones.update', [$this->project, $milestone]), [
            'reached' => true,
        ]);

        $response->assertRedirect();
        $updated = ProjectMilestone::on('tenant')->find($milestone->id);
        $this->assertNotNull($updated->reached_at);
    }

    public function test_jalon_sans_date_est_refuse(): void
    {
        $this->actingAs($this->owner);
        $response = $this->post(route('projects.milestones.store', $this->project), [
            'title' => 'Sans date',
        ]);
        $response->assertSessionHasErrors('due_date');
    }

    public function test_owner_peut_supprimer_un_jalon(): void
    {
        $milestone = ProjectMilestone::create([
            'project_id' => $this->project->id,
            'title' => 'À supprimer',
            'due_date' => '2026-12-31',
            'color' => '#EA580C',
        ]);

        $this->actingAs($this->owner);
        $response = $this->delete(route('projects.milestones.destroy', [$this->project, $milestone]));
        $response->assertRedirect();
        $this->assertSoftDeleted('project_milestones', ['id' => $milestone->id], 'tenant');
    }
}

// ─────────────────────────────────────────────────────────────────────────────
