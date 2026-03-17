<?php

namespace Tests\Feature\Projects;

use App\Models\Tenant\Project;
use App\Models\Tenant\ProjectMember;
use App\Models\Tenant\Task;
use App\Models\Tenant\TaskComment;
use App\Models\Tenant\User;
use Tests\TestCase;

/**
 * Tests de la route GET /projects/{project}/tasks/{task}
 * — endpoint JSON utilisé par le slide-over Alpine.js.
 *
 * Couvre :
 *   - Réponse JSON correcte pour un membre owner
 *   - Réponse JSON correcte pour un member
 *   - Réponse JSON correcte pour un viewer (lecture seule)
 *   - 403 pour un non-membre
 *   - Présence des commentaires dans la réponse
 *   - Présence des sous-tâches (compteurs)
 *   - Champs task : id, title, description, status, priority, assignee, due_date…
 */
class TaskShowTest extends TestCase
{
    private User $owner;

    private User $member;

    private User $viewer;

    private User $outsider;

    private Project $project;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner    = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->member   = User::factory()->create(['role' => 'user',  'status' => 'active']);
        $this->viewer   = User::factory()->create(['role' => 'user',  'status' => 'active']);
        $this->outsider = User::factory()->create(['role' => 'user',  'status' => 'active']);

        $this->project = Project::factory()->create(['created_by' => $this->owner->id]);

        ProjectMember::create(['project_id' => $this->project->id, 'user_id' => $this->owner->id,   'role' => 'owner']);
        ProjectMember::create(['project_id' => $this->project->id, 'user_id' => $this->member->id,  'role' => 'member']);
        ProjectMember::create(['project_id' => $this->project->id, 'user_id' => $this->viewer->id,  'role' => 'viewer']);

        $this->task = Task::factory()->create([
            'project_id'      => $this->project->id,
            'created_by'      => $this->owner->id,
            'assigned_to'     => $this->member->id,
            'title'           => 'Tâche de test slide-over',
            'description'     => 'Description détaillée.',
            'status'          => 'in_progress',
            'priority'        => 'high',
            'estimated_hours' => 8,
            'due_date'        => '2026-06-30',
        ]);
    }

    // ── Accès autorisés ───────────────────────────────────────────────

    public function test_owner_peut_voir_la_tache_en_json(): void
    {
        $this->actingAs($this->owner);

        $response = $this->getJson(route('projects.tasks.show', [$this->project, $this->task]));

        $response->assertOk()
            ->assertJsonStructure([
                'task' => [
                    'id', 'title', 'description', 'status', 'priority',
                    'start_date', 'due_date', 'estimated_hours', 'actual_hours',
                    'assignee', 'milestone', 'subtasks_total', 'subtasks_done',
                ],
                'comments',
            ])
            ->assertJsonPath('task.id', $this->task->id)
            ->assertJsonPath('task.title', 'Tâche de test slide-over')
            ->assertJsonPath('task.status', 'in_progress')
            ->assertJsonPath('task.priority', 'high')
            ->assertJsonPath('task.estimated_hours', 8);
    }

    public function test_member_peut_voir_la_tache(): void
    {
        $this->actingAs($this->member);

        $response = $this->getJson(route('projects.tasks.show', [$this->project, $this->task]));

        $response->assertOk()
            ->assertJsonPath('task.id', $this->task->id);
    }

    public function test_viewer_peut_voir_la_tache(): void
    {
        $this->actingAs($this->viewer);

        $response = $this->getJson(route('projects.tasks.show', [$this->project, $this->task]));

        $response->assertOk()
            ->assertJsonPath('task.id', $this->task->id);
    }

    // ── Accès refusé ──────────────────────────────────────────────────

    public function test_non_membre_obtient_403(): void
    {
        $this->actingAs($this->outsider);

        $response = $this->getJson(route('projects.tasks.show', [$this->project, $this->task]));

        $response->assertForbidden();
    }

    public function test_invité_non_connecté_redirigé(): void
    {
        $response = $this->getJson(route('projects.tasks.show', [$this->project, $this->task]));

        $response->assertUnauthorized();
    }

    // ── Données retournées ────────────────────────────────────────────

    public function test_assignee_present_dans_la_reponse(): void
    {
        $this->actingAs($this->owner);

        $response = $this->getJson(route('projects.tasks.show', [$this->project, $this->task]));

        $response->assertOk()
            ->assertJsonPath('task.assignee.id', $this->member->id)
            ->assertJsonPath('task.assignee.name', $this->member->name);
    }

    public function test_tache_sans_assignee_retourne_null(): void
    {
        $task = Task::factory()->create([
            'project_id'  => $this->project->id,
            'created_by'  => $this->owner->id,
            'assigned_to' => null,
        ]);

        $this->actingAs($this->owner);

        $response = $this->getJson(route('projects.tasks.show', [$this->project, $task]));

        $response->assertOk()
            ->assertJsonPath('task.assignee', null);
    }

    public function test_commentaires_inclus_dans_la_reponse(): void
    {
        TaskComment::create([
            'task_id' => $this->task->id,
            'user_id' => $this->owner->id,
            'body'    => 'Premier commentaire de test.',
        ]);
        TaskComment::create([
            'task_id' => $this->task->id,
            'user_id' => $this->member->id,
            'body'    => 'Deuxième commentaire.',
        ]);

        $this->actingAs($this->owner);

        $response = $this->getJson(route('projects.tasks.show', [$this->project, $this->task]));

        $response->assertOk()
            ->assertJsonCount(2, 'comments')
            ->assertJsonStructure(['comments' => [['id', 'author', 'body', 'created_at', 'is_mine']]]);
    }

    public function test_sans_commentaire_retourne_tableau_vide(): void
    {
        $this->actingAs($this->owner);

        $response = $this->getJson(route('projects.tasks.show', [$this->project, $this->task]));

        $response->assertOk()
            ->assertJsonCount(0, 'comments');
    }

    public function test_compteurs_sous_taches(): void
    {
        // Créer 3 sous-tâches dont 2 terminées
        Task::factory()->create(['project_id' => $this->project->id, 'created_by' => $this->owner->id, 'parent_task_id' => $this->task->id, 'status' => 'done']);
        Task::factory()->create(['project_id' => $this->project->id, 'created_by' => $this->owner->id, 'parent_task_id' => $this->task->id, 'status' => 'done']);
        Task::factory()->create(['project_id' => $this->project->id, 'created_by' => $this->owner->id, 'parent_task_id' => $this->task->id, 'status' => 'todo']);

        $this->actingAs($this->owner);

        $response = $this->getJson(route('projects.tasks.show', [$this->project, $this->task]));

        $response->assertOk()
            ->assertJsonPath('task.subtasks_total', 3)
            ->assertJsonPath('task.subtasks_done', 2);
    }

    public function test_is_mine_correct_selon_utilisateur(): void
    {
        TaskComment::create([
            'task_id' => $this->task->id,
            'user_id' => $this->owner->id,
            'body'    => 'Commentaire du owner.',
        ]);

        // Owner voit is_mine = true pour son propre commentaire
        $this->actingAs($this->owner);
        $response = $this->getJson(route('projects.tasks.show', [$this->project, $this->task]));
        $response->assertOk()
            ->assertJsonPath('comments.0.is_mine', true);

        // Member voit is_mine = false pour le commentaire du owner
        $this->actingAs($this->member);
        $response = $this->getJson(route('projects.tasks.show', [$this->project, $this->task]));
        $response->assertOk()
            ->assertJsonPath('comments.0.is_mine', false);
    }
}
