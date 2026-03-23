<?php

namespace Tests\Unit\Models;

use App\Models\Tenant\Project;
use App\Models\Tenant\ProjectMember;
use App\Models\Tenant\Task;
use App\Models\Tenant\User;
use Tests\TestCase;

/**
 * Tests unitaires du modèle Project.
 */
class ProjectModelTest extends TestCase
{
    public function test_visible_for_retourne_les_projets_du_membre(): void
    {
        $owner = User::factory()->create(['role' => 'user', 'status' => 'active']);
        $other = User::factory()->create(['role' => 'user', 'status' => 'active']);

        $project = Project::factory()->create(['created_by' => $owner->id]);
        ProjectMember::create(['project_id' => $project->id, 'user_id' => $owner->id, 'role' => 'owner']);

        $visible = Project::visibleFor($owner)->pluck('id');
        $this->assertTrue($visible->contains($project->id));

        $notVisible = Project::visibleFor($other)->pluck('id');
        $this->assertFalse($notVisible->contains($project->id));
    }

    public function test_visible_for_admin_retourne_tout(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $project = Project::factory()->create(['created_by' => $admin->id]);

        $visible = Project::visibleFor($admin)->pluck('id');
        $this->assertTrue($visible->contains($project->id));
    }

    public function test_progression_percent_retourne_0_sans_taches(): void
    {
        $project = Project::factory()->create(['created_by' => User::factory()->create(['role' => 'admin', 'status' => 'active'])->id]);
        $this->assertEquals(0, $project->progressionPercent());
    }

    public function test_progression_percent_calcule_correctement(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $project = Project::factory()->create(['created_by' => $user->id]);

        Task::factory()->create(['project_id' => $project->id, 'created_by' => $user->id, 'status' => 'done',        'parent_task_id' => null]);
        Task::factory()->create(['project_id' => $project->id, 'created_by' => $user->id, 'status' => 'done',        'parent_task_id' => null]);
        Task::factory()->create(['project_id' => $project->id, 'created_by' => $user->id, 'status' => 'in_progress', 'parent_task_id' => null]);
        Task::factory()->create(['project_id' => $project->id, 'created_by' => $user->id, 'status' => 'todo',        'parent_task_id' => null]);

        $this->assertEquals(50, $project->progressionPercent());
    }

    public function test_is_member_retourne_vrai_pour_un_membre(): void
    {
        $user = User::factory()->create(['role' => 'user', 'status' => 'active']);
        $project = Project::factory()->create(['created_by' => $user->id]);
        ProjectMember::create(['project_id' => $project->id, 'user_id' => $user->id, 'role' => 'owner']);

        $this->assertTrue($project->isMember($user));
    }

    public function test_is_member_retourne_faux_pour_un_non_membre(): void
    {
        $user = User::factory()->create(['role' => 'user', 'status' => 'active']);
        $outsider = User::factory()->create(['role' => 'user', 'status' => 'active']);
        $project = Project::factory()->create(['created_by' => $user->id]);
        ProjectMember::create(['project_id' => $project->id, 'user_id' => $user->id, 'role' => 'owner']);

        $this->assertFalse($project->isMember($outsider));
    }
}
