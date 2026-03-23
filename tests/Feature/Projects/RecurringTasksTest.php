<?php

namespace Tests\Feature\Projects;

use App\Models\Tenant\Project;
use App\Models\Tenant\ProjectMember;
use App\Models\Tenant\Task;
use App\Models\Tenant\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Tests de la récurrence des tâches.
 *
 * Couvre :
 *   - Création d'une tâche avec récurrence via l'API
 *   - Génération d'occurrences hebdomadaires
 *   - Génération d'occurrences mensuelles
 *   - Respect de la date de fin de récurrence
 *   - Pas de doublon si l'occurrence existe déjà
 *   - Option --tenant sur la commande
 */
class RecurringTasksTest extends TestCase
{
    private User $owner;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->project = Project::factory()->create(['created_by' => $this->owner->id]);
        ProjectMember::create([
            'project_id' => $this->project->id,
            'user_id' => $this->owner->id,
            'role' => 'owner',
        ]);
    }

    // ── Création via API ──────────────────────────────────────────────────

    public function test_creation_tache_avec_recurrence_hebdomadaire(): void
    {
        $this->actingAs($this->owner);

        $response = $this->postJson(
            route('projects.tasks.store', $this->project),
            [
                'title' => 'Réunion hebdo',
                'status' => 'todo',
                'priority' => 'medium',
                'due_date' => now()->addDays(7)->format('Y-m-d'),
                'recurrence_type' => 'weekly',
                'recurrence_every' => 1,
                'recurrence_ends' => now()->addMonths(3)->format('Y-m-d'),
            ]
        );

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('tasks', [
            'title' => 'Réunion hebdo',
            'recurrence_type' => 'weekly',
            'recurrence_every' => 1,
        ], 'tenant');
    }

    // ── Génération d'occurrences ──────────────────────────────────────────

    public function test_generation_occurrence_hebdomadaire(): void
    {
        // Tâche parente avec due_date = aujourd'hui
        $parent = Task::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->owner->id,
            'title' => 'Réunion hebdo',
            'status' => 'todo',
            'due_date' => Carbon::today(),
            'recurrence_type' => 'weekly',
            'recurrence_every' => 1,
            'recurrence_ends' => Carbon::today()->addMonths(2),
        ]);

        Artisan::call('pladigit:generate-recurring-tasks');

        // Une occurrence doit être créée pour la semaine prochaine
        $this->assertDatabaseHas('tasks', [
            'recurrence_parent_id' => $parent->id,
            'title' => 'Réunion hebdo',
            'recurrence_type' => null, // Les occurrences ne récurent pas
        ], 'tenant');

        $occurrence = Task::on('tenant')
            ->where('recurrence_parent_id', $parent->id)
            ->first();

        $this->assertNotNull($occurrence);
        $this->assertEquals(
            Carbon::today()->addWeek()->toDateString(),
            $occurrence->due_date->toDateString()
        );
    }

    public function test_generation_occurrence_mensuelle(): void
    {
        $parent = Task::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->owner->id,
            'title' => 'Point mensuel élus',
            'status' => 'todo',
            'due_date' => Carbon::today(),
            'recurrence_type' => 'monthly',
            'recurrence_every' => 1,
            'recurrence_ends' => Carbon::today()->addYear(),
        ]);

        Artisan::call('pladigit:generate-recurring-tasks');

        $occurrence = Task::on('tenant')
            ->where('recurrence_parent_id', $parent->id)
            ->first();

        $this->assertNotNull($occurrence);
        $this->assertEquals(
            Carbon::today()->addMonth()->toDateString(),
            $occurrence->due_date->toDateString()
        );
    }

    public function test_pas_de_generation_apres_date_de_fin(): void
    {
        // Tâche dont la récurrence est déjà terminée
        $parent = Task::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->owner->id,
            'title' => 'Tâche expirée',
            'status' => 'todo',
            'due_date' => Carbon::today(),
            'recurrence_type' => 'weekly',
            'recurrence_every' => 1,
            'recurrence_ends' => Carbon::yesterday(), // Déjà passée
        ]);

        Artisan::call('pladigit:generate-recurring-tasks');

        $this->assertDatabaseMissing('tasks', [
            'recurrence_parent_id' => $parent->id,
        ], 'tenant');
    }

    public function test_pas_de_doublon_si_occurrence_existe(): void
    {
        $parent = Task::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->owner->id,
            'title' => 'Réunion hebdo',
            'status' => 'todo',
            'due_date' => Carbon::today(),
            'recurrence_type' => 'weekly',
            'recurrence_every' => 1,
            'recurrence_ends' => Carbon::today()->addDays(8), // J+7 OK, J+14 hors limite
        ]);

        // Pré-créer l'occurrence à J+7
        Task::on('tenant')->create([
            'project_id' => $parent->project_id,
            'created_by' => $parent->created_by,
            'title' => $parent->title,
            'status' => 'todo',
            'priority' => $parent->priority,
            'due_date' => Carbon::today()->addWeek(),
            'sort_order' => 0,
            'recurrence_type' => null,
            'recurrence_parent_id' => $parent->id,
        ]);

        // La commande voit last=J+7, calcule nextDue=J+14 > recurrence_ends → rien de créé
        Artisan::call('pladigit:generate-recurring-tasks');

        $count = Task::on('tenant')
            ->where('recurrence_parent_id', $parent->id)
            ->count();

        $this->assertEquals(1, $count, 'Une seule occurrence doit être générée');
    }

    public function test_commande_avec_option_tenant_inexistant(): void
    {
        $exitCode = Artisan::call('pladigit:generate-recurring-tasks', [
            '--tenant' => 'slug-qui-nexiste-pas',
        ]);

        $this->assertEquals(1, $exitCode);
    }
}
