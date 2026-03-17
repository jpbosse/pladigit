<?php

namespace Tests\Feature\Projects;

use App\Models\Tenant\Project;
use App\Models\Tenant\ProjectMember;
use App\Models\Tenant\Task;
use App\Models\Tenant\User;
use Tests\TestCase;

/**
 * Tests CRUD des tâches.
 */
class TaskCrudTest extends TestCase
{
    private User $owner;

    private User $member;

    private User $viewer;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['role' => 'admin',  'status' => 'active']);
        $this->member = User::factory()->create(['role' => 'user',   'status' => 'active']);
        $this->viewer = User::factory()->create(['role' => 'user',   'status' => 'active']);

        $this->project = Project::factory()->create(['created_by' => $this->owner->id]);
        ProjectMember::create(['project_id' => $this->project->id, 'user_id' => $this->owner->id,  'role' => 'owner']);
        ProjectMember::create(['project_id' => $this->project->id, 'user_id' => $this->member->id, 'role' => 'member']);
        ProjectMember::create(['project_id' => $this->project->id, 'user_id' => $this->viewer->id, 'role' => 'viewer']);
    }

    public function test_member_peut_creer_une_tache(): void
    {
        $this->actingAs($this->member);

        $response = $this->post(route('projects.tasks.store', $this->project), [
            'title' => 'Ma nouvelle tâche',
            'status' => 'todo',
            'priority' => 'medium',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tasks', [
            'project_id' => $this->project->id,
            'title' => 'Ma nouvelle tâche',
            'created_by' => $this->member->id,
        ], 'tenant');
    }

    public function test_viewer_ne_peut_pas_creer_une_tache(): void
    {
        $this->actingAs($this->viewer);

        $response = $this->post(route('projects.tasks.store', $this->project), [
            'title' => 'Tâche interdite',
            'status' => 'todo',
            'priority' => 'medium',
        ]);

        $response->assertForbidden();
    }

    public function test_owner_peut_modifier_nimporte_quelle_tache(): void
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->member->id,
            'assigned_to' => $this->member->id,
            'status' => 'todo',
            'priority' => 'low',
        ]);

        $this->actingAs($this->owner);
        $response = $this->patch(route('projects.tasks.update', [$this->project, $task]), [
            'status' => 'in_progress',
            'priority' => 'high',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'in_progress', 'priority' => 'high'], 'tenant');
    }

    public function test_member_peut_modifier_sa_propre_tache(): void
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->member->id,
            'assigned_to' => $this->member->id,
            'status' => 'todo',
            'priority' => 'low',
        ]);

        $this->actingAs($this->member);
        $response = $this->patch(route('projects.tasks.update', [$this->project, $task]), ['status' => 'in_progress']);
        $response->assertRedirect();
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'in_progress'], 'tenant');
    }

    public function test_member_ne_peut_pas_modifier_une_tache_qui_ne_lui_appartient_pas(): void
    {
        $other = User::factory()->create(['role' => 'user', 'status' => 'active']);
        ProjectMember::create(['project_id' => $this->project->id, 'user_id' => $other->id, 'role' => 'member']);

        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->owner->id,
            'assigned_to' => $this->owner->id,
            'status' => 'todo',
        ]);

        $this->actingAs($other);
        $response = $this->patch(route('projects.tasks.update', [$this->project, $task]), ['status' => 'done']);
        $response->assertForbidden();
    }

    public function test_statut_de_tache_invalide_est_refuse(): void
    {
        $task = Task::factory()->create(['project_id' => $this->project->id, 'created_by' => $this->owner->id]);

        $this->actingAs($this->owner);
        $response = $this->patch(route('projects.tasks.update', [$this->project, $task]), ['status' => 'fantome']);
        $response->assertSessionHasErrors('status');
    }

    public function test_owner_peut_supprimer_une_tache(): void
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->owner->id,
        ]);

        $this->actingAs($this->owner);
        $response = $this->delete(route('projects.tasks.destroy', [$this->project, $task]));
        $response->assertRedirect();
        $this->assertSoftDeleted('tasks', ['id' => $task->id], 'tenant');
    }

    public function test_sort_order_incremente_en_fin_de_colonne(): void
    {
        Task::factory()->create(['project_id' => $this->project->id, 'created_by' => $this->owner->id, 'status' => 'todo', 'sort_order' => 0]);
        Task::factory()->create(['project_id' => $this->project->id, 'created_by' => $this->owner->id, 'status' => 'todo', 'sort_order' => 1]);

        $this->actingAs($this->member);
        $this->post(route('projects.tasks.store', $this->project), [
            'title' => 'Tâche en fin', 'status' => 'todo', 'priority' => 'low',
        ]);

        $last = Task::on('tenant')
            ->where('project_id', $this->project->id)
            ->where('title', 'Tâche en fin')
            ->first();

        $this->assertEquals(2, $last->sort_order);
    }

    public function test_tache_avec_date_fin_avant_debut_est_refusee(): void
    {
        $this->actingAs($this->member);
        $response = $this->post(route('projects.tasks.store', $this->project), [
            'title' => 'Dates invalides',
            'status' => 'todo',
            'priority' => 'medium',
            'start_date' => '2026-12-31',
            'due_date' => '2026-01-01',
        ]);
        $response->assertSessionHasErrors('due_date');
    }
}

// ─────────────────────────────────────────────────────────────────────────────
