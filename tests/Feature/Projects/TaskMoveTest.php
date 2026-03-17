<?php

namespace Tests\Feature\Projects;

use App\Models\Tenant\Project;
use App\Models\Tenant\ProjectMember;
use App\Models\Tenant\Task;
use App\Models\Tenant\User;
use Tests\TestCase;

/**
 * Tests du déplacement Kanban (KanbanController::move / reorder).
 */
class TaskMoveTest extends TestCase
{
    private User $owner;

    private User $viewer;

    private Project $project;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->viewer = User::factory()->create(['role' => 'user',  'status' => 'active']);

        $this->project = Project::factory()->create(['created_by' => $this->owner->id]);
        ProjectMember::create(['project_id' => $this->project->id, 'user_id' => $this->owner->id,  'role' => 'owner']);
        ProjectMember::create(['project_id' => $this->project->id, 'user_id' => $this->viewer->id, 'role' => 'viewer']);

        $this->task = Task::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->owner->id,
            'status' => 'todo',
            'sort_order' => 0,
        ]);
    }

    public function test_owner_peut_deplacer_une_tache_kanban(): void
    {
        $this->actingAs($this->owner);

        $response = $this->patchJson(route('projects.kanban.move', $this->project), [
            'task_id' => $this->task->id,
            'new_status' => 'in_progress',
            'sort_order' => 0,
        ]);

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseHas('tasks', ['id' => $this->task->id, 'status' => 'in_progress'], 'tenant');
    }

    public function test_viewer_ne_peut_pas_deplacer_une_tache(): void
    {
        $this->actingAs($this->viewer);

        $response = $this->patchJson(route('projects.kanban.move', $this->project), [
            'task_id' => $this->task->id,
            'new_status' => 'done',
            'sort_order' => 0,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('tasks', ['id' => $this->task->id, 'status' => 'todo'], 'tenant');
    }

    public function test_sort_order_mis_a_jour_apres_deplacement(): void
    {
        $task2 = Task::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->owner->id,
            'status' => 'in_progress',
            'sort_order' => 0,
        ]);

        $this->actingAs($this->owner);
        $this->patchJson(route('projects.kanban.move', $this->project), [
            'task_id' => $this->task->id,
            'new_status' => 'in_progress',
            'sort_order' => 1,
            'ordered_ids' => [$task2->id, $this->task->id],
        ]);

        $this->assertDatabaseHas('tasks', ['id' => $task2->id,    'sort_order' => 0], 'tenant');
        $this->assertDatabaseHas('tasks', ['id' => $this->task->id, 'sort_order' => 1], 'tenant');
    }

    public function test_statut_invalide_est_refuse(): void
    {
        $this->actingAs($this->owner);

        $response = $this->patchJson(route('projects.kanban.move', $this->project), [
            'task_id' => $this->task->id,
            'new_status' => 'statut_inexistant',
            'sort_order' => 0,
        ]);

        $response->assertUnprocessable();
    }
}
