<?php

namespace Tests\Feature\Projects;

use App\Models\Tenant\Project;
use App\Models\Tenant\ProjectMember;
use App\Models\Tenant\User;
use Tests\TestCase;

/**
 * Tests de gestion des membres.
 */
class ProjectMemberTest extends TestCase
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
        ProjectMember::create(['project_id' => $this->project->id, 'user_id' => $this->owner->id, 'role' => 'owner']);
    }

    public function test_owner_peut_ajouter_un_membre(): void
    {
        $newUser = User::factory()->create(['role' => 'user', 'status' => 'active']);
        $this->actingAs($this->owner);

        $response = $this->post(route('projects.members.store', $this->project), [
            'user_id' => $newUser->id,
            'role' => 'member',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('project_members', [
            'project_id' => $this->project->id,
            'user_id' => $newUser->id,
            'role' => 'member',
        ], 'tenant');
    }

    public function test_member_ne_peut_pas_ajouter_un_membre(): void
    {
        ProjectMember::create(['project_id' => $this->project->id, 'user_id' => $this->member->id, 'role' => 'member']);
        $newUser = User::factory()->create(['role' => 'user', 'status' => 'active']);
        $this->actingAs($this->member);

        $response = $this->post(route('projects.members.store', $this->project), [
            'user_id' => $newUser->id,
            'role' => 'member',
        ]);

        $response->assertForbidden();
    }

    public function test_owner_peut_retirer_un_membre(): void
    {
        ProjectMember::create(['project_id' => $this->project->id, 'user_id' => $this->member->id, 'role' => 'member']);
        $this->actingAs($this->owner);

        $response = $this->delete(route('projects.members.destroy', [$this->project, $this->member]));
        $response->assertRedirect();

        $this->assertDatabaseMissing('project_members', [
            'project_id' => $this->project->id,
            'user_id' => $this->member->id,
        ], 'tenant');
    }

    public function test_impossible_de_retirer_le_dernier_owner(): void
    {
        $this->actingAs($this->owner);

        $response = $this->delete(route('projects.members.destroy', [$this->project, $this->owner]));
        $response->assertRedirect();
        $response->assertSessionHasErrors('member');

        // L'owner doit toujours exister
        $this->assertDatabaseHas('project_members', [
            'project_id' => $this->project->id,
            'user_id' => $this->owner->id,
            'role' => 'owner',
        ], 'tenant');
    }

    public function test_ajouter_un_membre_deja_present_met_a_jour_son_role(): void
    {
        ProjectMember::create(['project_id' => $this->project->id, 'user_id' => $this->member->id, 'role' => 'member']);
        $this->actingAs($this->owner);

        $this->post(route('projects.members.store', $this->project), [
            'user_id' => $this->member->id,
            'role' => 'viewer',
        ]);

        $this->assertDatabaseHas('project_members', [
            'project_id' => $this->project->id,
            'user_id' => $this->member->id,
            'role' => 'viewer',
        ], 'tenant');

        // Pas de doublon
        $count = ProjectMember::on('tenant')
            ->where('project_id', $this->project->id)
            ->where('user_id', $this->member->id)
            ->count();
        $this->assertEquals(1, $count);
    }
}
