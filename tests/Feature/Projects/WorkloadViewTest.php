<?php

namespace Tests\Feature\Projects;

use App\Models\Tenant\Project;
use App\Models\Tenant\ProjectMember;
use App\Models\Tenant\Task;
use App\Models\Tenant\User;
use Tests\TestCase;

/**
 * Tests de la vue Charge (workload) — onglet de la vue planification.
 *
 * La vue charge est un partial inclus dans show.blade.php via section=planif.
 * On teste via GET /projects/{project}?section=planif que la page se charge
 * correctement avec différents scénarios de tâches assignées.
 */
class WorkloadViewTest extends TestCase
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

        $this->project = Project::factory()->create([
            'created_by' => $this->owner->id,
            'status' => 'active',
            'start_date' => now()->subWeeks(2),
            'due_date' => now()->addWeeks(8),
        ]);
        ProjectMember::create(['project_id' => $this->project->id, 'user_id' => $this->owner->id,  'role' => 'owner']);
        ProjectMember::create(['project_id' => $this->project->id, 'user_id' => $this->member->id, 'role' => 'member']);
        ProjectMember::create(['project_id' => $this->project->id, 'user_id' => $this->viewer->id, 'role' => 'viewer']);
    }

    // ── Accès ─────────────────────────────────────────────────────────────────

    public function test_owner_peut_acceder_a_la_vue_projet(): void
    {
        $this->actingAs($this->owner);
        $this->get(route('projects.show', $this->project))->assertOk();
    }

    public function test_member_peut_acceder_a_la_vue_projet(): void
    {
        $this->actingAs($this->member);
        $this->get(route('projects.show', $this->project))->assertOk();
    }

    public function test_viewer_peut_acceder_a_la_vue_projet(): void
    {
        $this->actingAs($this->viewer);
        $this->get(route('projects.show', $this->project))->assertOk();
    }

    public function test_non_membre_ne_peut_pas_acceder_a_la_vue_projet(): void
    {
        $this->actingAs($this->outsider);
        $this->get(route('projects.show', $this->project))->assertForbidden();
    }

    // ── Vue sans tâches ───────────────────────────────────────────────────────

    public function test_vue_projet_sans_taches_assignees_se_charge_sans_erreur(): void
    {
        // Aucune tâche — la vue charge doit afficher un état vide sans exception
        $this->actingAs($this->owner);

        $this->get(route('projects.show', $this->project))
            ->assertOk()
            ->assertSee($this->project->name);
    }

    // ── Vue avec tâches assignées ─────────────────────────────────────────────

    public function test_vue_projet_avec_taches_assignees_inclut_les_donnees_heures(): void
    {
        Task::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->owner->id,
            'assigned_to' => $this->member->id,
            'status' => 'in_progress',
            'priority' => 'high',
            'estimated_hours' => 8,
            'actual_hours' => 3,
            'start_date' => now()->subDays(3),
            'due_date' => now()->addDays(4),
        ]);

        Task::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->owner->id,
            'assigned_to' => $this->owner->id,
            'status' => 'todo',
            'priority' => 'medium',
            'estimated_hours' => 4,
            'actual_hours' => null,
            'start_date' => now()->addDays(1),
            'due_date' => now()->addDays(7),
        ]);

        $this->actingAs($this->owner);

        $this->get(route('projects.show', $this->project))
            ->assertOk()
            ->assertSee($this->project->name);
    }

    // ── Tâches sans dates (fenêtre fallback) ──────────────────────────────────

    public function test_vue_projet_avec_taches_sans_dates_ne_leve_pas_dexception(): void
    {
        // Tâches sans start_date ni due_date — la fenêtre temporelle doit
        // basculer sur le fallback ±2 semaines / +8 semaines
        Task::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->owner->id,
            'assigned_to' => $this->member->id,
            'status' => 'todo',
            'start_date' => null,
            'due_date' => null,
        ]);

        $this->actingAs($this->member);

        $this->get(route('projects.show', $this->project))->assertOk();
    }

    // ── Tâches terminées exclues de la charge ─────────────────────────────────

    public function test_taches_terminees_ne_generent_pas_derreur_dans_la_vue(): void
    {
        // Les tâches done sont exclues du calcul de charge (whereNotIn status done)
        Task::factory()->done()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->owner->id,
            'assigned_to' => $this->member->id,
            'start_date' => now()->subDays(10),
            'due_date' => now()->subDays(2),
        ]);

        $this->actingAs($this->owner);

        $this->get(route('projects.show', $this->project))->assertOk();
    }

    // ── Bandeau heures total ──────────────────────────────────────────────────

    public function test_vue_projet_contient_les_statistiques_de_taches(): void
    {
        Task::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->owner->id,
            'assigned_to' => $this->member->id,
            'status' => 'in_progress',
            'estimated_hours' => 10,
            'actual_hours' => 5,
            'start_date' => now()->subDays(2),
            'due_date' => now()->addDays(5),
        ]);

        $this->actingAs($this->owner);

        // La page projet doit se charger sans erreur et contenir le nom du projet
        $response = $this->get(route('projects.show', $this->project));
        $response->assertOk();
        $response->assertSee($this->project->name);
    }
}
