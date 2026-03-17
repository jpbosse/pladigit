<?php

namespace Tests\Feature\Projects;

use App\Models\Tenant\Project;
use App\Models\Tenant\ProjectMember;
use App\Models\Tenant\Task;
use App\Models\Tenant\TaskComment;
use App\Models\Tenant\User;
use Tests\TestCase;

/**
 * Tests des commentaires de tâches.
 */
class TaskCommentTest extends TestCase
{
    private User $owner;

    private User $member;

    private User $viewer;

    private Project $project;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->member = User::factory()->create(['role' => 'user',  'status' => 'active']);
        $this->viewer = User::factory()->create(['role' => 'user',  'status' => 'active']);

        $this->project = Project::factory()->create(['created_by' => $this->owner->id]);
        ProjectMember::create(['project_id' => $this->project->id, 'user_id' => $this->owner->id,  'role' => 'owner']);
        ProjectMember::create(['project_id' => $this->project->id, 'user_id' => $this->member->id, 'role' => 'member']);
        ProjectMember::create(['project_id' => $this->project->id, 'user_id' => $this->viewer->id, 'role' => 'viewer']);

        $this->task = Task::factory()->create(['project_id' => $this->project->id, 'created_by' => $this->owner->id]);
    }

    public function test_member_peut_commenter(): void
    {
        $this->actingAs($this->member);
        $response = $this->post(route('projects.tasks.comments.store', [$this->project, $this->task]), ['body' => 'Mon commentaire']);
        $response->assertRedirect();
        $this->assertDatabaseHas('task_comments', ['task_id' => $this->task->id, 'user_id' => $this->member->id, 'body' => 'Mon commentaire'], 'tenant');
    }

    public function test_viewer_ne_peut_pas_commenter(): void
    {
        $this->actingAs($this->viewer);
        $response = $this->post(route('projects.tasks.comments.store', [$this->project, $this->task]), ['body' => 'Commentaire interdit']);
        $response->assertForbidden();
    }

    public function test_auteur_peut_supprimer_son_commentaire(): void
    {
        $comment = TaskComment::create(['task_id' => $this->task->id, 'user_id' => $this->member->id, 'body' => 'À supprimer']);
        $this->actingAs($this->member);
        $response = $this->delete(route('projects.tasks.comments.destroy', [$this->project, $this->task, $comment]));
        $response->assertRedirect();
        $this->assertSoftDeleted('task_comments', ['id' => $comment->id], 'tenant');
    }

    public function test_non_auteur_ne_peut_pas_supprimer_le_commentaire(): void
    {
        $other = User::factory()->create(['role' => 'user', 'status' => 'active']);
        ProjectMember::create(['project_id' => $this->project->id, 'user_id' => $other->id, 'role' => 'member']);

        $comment = TaskComment::create(['task_id' => $this->task->id, 'user_id' => $this->member->id, 'body' => 'Protégé']);
        $this->actingAs($other);
        $response = $this->delete(route('projects.tasks.comments.destroy', [$this->project, $this->task, $comment]));
        $response->assertForbidden();
    }

    public function test_commentaire_vide_est_refuse(): void
    {
        $this->actingAs($this->member);
        $response = $this->post(route('projects.tasks.comments.store', [$this->project, $this->task]), ['body' => '']);
        $response->assertSessionHasErrors('body');
    }
}

// ─────────────────────────────────────────────────────────────────────────────
