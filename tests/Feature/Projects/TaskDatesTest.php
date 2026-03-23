<?php

namespace Tests\Feature\Projects;

use App\Models\Tenant\Project;
use App\Models\Tenant\ProjectMember;
use App\Models\Tenant\Task;
use App\Models\Tenant\User;
use Tests\TestCase;

/**
 * Tests de la mise à jour des dates de tâches via le Gantt.
 *
 * Route : PATCH /projects/{project}/tasks/{task}/dates
 * Contrôleur : TaskController::updateDates()
 *
 * Couvre :
 *   - Owner peut modifier les dates
 *   - Member peut modifier les dates
 *   - Viewer ne peut pas modifier les dates (403)
 *   - Non-membre ne peut pas modifier (403)
 *   - Validation : start_date requis
 *   - Validation : due_date doit être >= start_date
 *   - Les dates sont correctement persistées
 */
class TaskDatesTest extends TestCase
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

        $this->owner = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->member = User::factory()->create(['role' => 'user',  'status' => 'active']);
        $this->viewer = User::factory()->create(['role' => 'user',  'status' => 'active']);
        $this->outsider = User::factory()->create(['role' => 'user',  'status' => 'active']);

        $this->project = Project::factory()->create(['created_by' => $this->owner->id]);

        ProjectMember::create(['project_id' => $this->project->id, 'user_id' => $this->owner->id,  'role' => 'owner']);
        ProjectMember::create(['project_id' => $this->project->id, 'user_id' => $this->member->id, 'role' => 'member']);
        ProjectMember::create(['project_id' => $this->project->id, 'user_id' => $this->viewer->id, 'role' => 'viewer']);

        $this->task = Task::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->owner->id,
            'start_date' => '2026-04-01',
            'due_date' => '2026-04-15',
        ]);
    }

    // ── Owner ─────────────────────────────────────────────────────────

    public function test_owner_peut_modifier_les_dates(): void
    {
        $this->actingAs($this->owner);

        $response = $this->patchJson(
            route('projects.tasks.dates', [$this->project, $this->task]),
            ['start_date' => '2026-05-01', 'due_date' => '2026-05-20']
        );

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('tasks', [
            'id' => $this->task->id,
            'start_date' => '2026-05-01',
            'due_date' => '2026-05-20',
        ], 'tenant');
    }

    // ── Member ────────────────────────────────────────────────────────

    public function test_member_peut_modifier_les_dates(): void
    {
        $this->actingAs($this->member);

        $response = $this->patchJson(
            route('projects.tasks.dates', [$this->project, $this->task]),
            ['start_date' => '2026-06-01', 'due_date' => '2026-06-10']
        );

        $response->assertOk()->assertJson(['success' => true]);
    }

    // ── Viewer / non-membre ───────────────────────────────────────────

    public function test_viewer_ne_peut_pas_modifier_les_dates(): void
    {
        $this->actingAs($this->viewer);

        $response = $this->patchJson(
            route('projects.tasks.dates', [$this->project, $this->task]),
            ['start_date' => '2026-06-01', 'due_date' => '2026-06-10']
        );

        $response->assertForbidden();
    }

    public function test_non_membre_ne_peut_pas_modifier_les_dates(): void
    {
        $this->actingAs($this->outsider);

        $response = $this->patchJson(
            route('projects.tasks.dates', [$this->project, $this->task]),
            ['start_date' => '2026-06-01', 'due_date' => '2026-06-10']
        );

        $response->assertForbidden();
    }

    // ── Validation ────────────────────────────────────────────────────

    public function test_start_date_est_requise(): void
    {
        $this->actingAs($this->owner);

        $response = $this->patchJson(
            route('projects.tasks.dates', [$this->project, $this->task]),
            ['due_date' => '2026-06-10']
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('start_date');
    }

    public function test_due_date_est_requise(): void
    {
        $this->actingAs($this->owner);

        $response = $this->patchJson(
            route('projects.tasks.dates', [$this->project, $this->task]),
            ['start_date' => '2026-06-01']
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('due_date');
    }

    public function test_due_date_doit_etre_apres_start_date(): void
    {
        $this->actingAs($this->owner);

        $response = $this->patchJson(
            route('projects.tasks.dates', [$this->project, $this->task]),
            ['start_date' => '2026-06-30', 'due_date' => '2026-06-01']
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('due_date');
    }

    public function test_due_date_egale_start_date_est_valide(): void
    {
        $this->actingAs($this->owner);

        $response = $this->patchJson(
            route('projects.tasks.dates', [$this->project, $this->task]),
            ['start_date' => '2026-06-15', 'due_date' => '2026-06-15']
        );

        $response->assertOk()->assertJson(['success' => true]);
    }
}
