<?php

namespace Tests\Feature\Projects;

use App\Models\Tenant\Project;
use App\Models\Tenant\ProjectMember;
use App\Models\Tenant\Task;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tests des dépendances Fin→Fin entre tâches.
 *
 * Couvre :
 *   - Ajout d'une dépendance valide
 *   - Blocage dur : impossible de passer B à "done" si A ne l'est pas
 *   - Passage à "done" autorisé quand A est terminée
 *   - Détection de cycle (A→B→A refusé)
 *   - Auto-dépendance refusée
 *   - Dépendance inter-projet refusée
 *   - Suppression d'une dépendance
 *   - show() JSON inclut les prédécesseurs
 *   - Statuts autres que "done" ne sont pas bloqués (Fin→Fin uniquement)
 */
class TaskDependencyTest extends TestCase
{
    private User $owner;

    private User $member;

    private User $outsider;

    private Project $project;

    private Task $taskA;

    private Task $taskB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['role' => 'admin',  'status' => 'active']);
        $this->member = User::factory()->create(['role' => 'user',   'status' => 'active']);
        $this->outsider = User::factory()->create(['role' => 'user',   'status' => 'active']);

        $this->project = Project::factory()->create([
            'created_by' => $this->owner->id,
            'name' => 'Projet Dépendances',
            'start_date' => '2026-01-01',
            'due_date' => '2026-12-31',
        ]);

        ProjectMember::create(['project_id' => $this->project->id, 'user_id' => $this->owner->id,  'role' => 'owner']);
        ProjectMember::create(['project_id' => $this->project->id, 'user_id' => $this->member->id, 'role' => 'member']);

        $this->taskA = Task::on('tenant')->create([
            'project_id' => $this->project->id,
            'created_by' => $this->owner->id,
            'title' => 'A — Marché public',
            'status' => 'todo',
            'priority' => 'high',
            'start_date' => '2026-02-01',
            'due_date' => '2026-03-31',
        ]);

        $this->taskB = Task::on('tenant')->create([
            'project_id' => $this->project->id,
            'created_by' => $this->owner->id,
            'title' => 'B — Livraison',
            'status' => 'in_progress',
            'priority' => 'medium',
            'start_date' => '2026-04-01',
            'due_date' => '2026-05-31',
        ]);
    }

    // ── Ajout de dépendance ───────────────────────────────────────────────

    public function test_ajout_dependance_valide(): void
    {
        $this->actingAs($this->owner);

        $response = $this->postJson(
            route('projects.tasks.dependencies.store', [$this->project, $this->taskB]),
            ['predecessor_id' => $this->taskA->id]
        );

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('task_dependencies', [
            'task_id' => $this->taskB->id,
            'depends_on_task_id' => $this->taskA->id,
        ], 'tenant');
    }

    public function test_ajout_doublon_silencieux(): void
    {
        // Insérer la dépendance une première fois
        DB::connection('tenant')->table('task_dependencies')->insert([
            'task_id' => $this->taskB->id,
            'depends_on_task_id' => $this->taskA->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->owner);

        // Deuxième appel → ne doit pas lever d'exception UNIQUE
        $this->postJson(
            route('projects.tasks.dependencies.store', [$this->project, $this->taskB]),
            ['predecessor_id' => $this->taskA->id]
        )->assertOk();

        $this->assertSame(1, DB::connection('tenant')->table('task_dependencies')
            ->where('task_id', $this->taskB->id)
            ->where('depends_on_task_id', $this->taskA->id)
            ->count());
    }

    // ── Blocage Fin→Fin ───────────────────────────────────────────────────

    public function test_passer_done_bloque_si_predecesseur_non_termine(): void
    {
        // B dépend de A (A est "todo")
        DB::connection('tenant')->table('task_dependencies')->insert([
            'task_id' => $this->taskB->id,
            'depends_on_task_id' => $this->taskA->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->owner);

        $response = $this->patchJson(
            route('projects.tasks.update', [$this->project, $this->taskB]),
            ['status' => 'done']
        );

        $response->assertStatus(422);
        $response->assertJsonPath('error', fn ($v) => str_contains($v, 'A — Marché public'));

        // Le statut ne doit pas avoir changé
        $this->assertSame('in_progress', $this->taskB->fresh()->status);
    }

    public function test_passer_done_autorise_si_predecesseur_termine(): void
    {
        // Terminer A d'abord
        $this->taskA->update(['status' => 'done']);

        DB::connection('tenant')->table('task_dependencies')->insert([
            'task_id' => $this->taskB->id,
            'depends_on_task_id' => $this->taskA->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->owner);

        $response = $this->patchJson(
            route('projects.tasks.update', [$this->project, $this->taskB]),
            ['status' => 'done']
        );

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertSame('done', $this->taskB->fresh()->status);
    }

    public function test_passer_in_progress_non_bloque_par_dependance(): void
    {
        // La règle Fin→Fin ne bloque que le passage à "done"
        // Passer à "in_progress" doit toujours être autorisé
        $taskC = Task::on('tenant')->create([
            'project_id' => $this->project->id,
            'created_by' => $this->owner->id,
            'title' => 'C — Réception',
            'status' => 'todo',
            'priority' => 'medium',
            'start_date' => '2026-06-01',
            'due_date' => '2026-07-31',
        ]);

        DB::connection('tenant')->table('task_dependencies')->insert([
            'task_id' => $taskC->id,
            'depends_on_task_id' => $this->taskA->id, // A pas terminée
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->owner);

        $this->patchJson(
            route('projects.tasks.update', [$this->project, $taskC]),
            ['status' => 'in_progress']
        )->assertOk()->assertJson(['success' => true]);

        $this->assertSame('in_progress', $taskC->fresh()->status);
    }

    // ── Détection de cycle ────────────────────────────────────────────────

    public function test_dependance_circulaire_refusee(): void
    {
        // A dépend de B
        DB::connection('tenant')->table('task_dependencies')->insert([
            'task_id' => $this->taskA->id,
            'depends_on_task_id' => $this->taskB->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->owner);

        // Tenter de faire dépendre B de A → cycle A→B→A
        $response = $this->postJson(
            route('projects.tasks.dependencies.store', [$this->project, $this->taskB]),
            ['predecessor_id' => $this->taskA->id]
        );

        $response->assertStatus(422);
        $response->assertJsonPath('error', fn ($v) => str_contains($v, 'circulaire'));
    }

    public function test_auto_dependance_refusee(): void
    {
        $this->actingAs($this->owner);

        $response = $this->postJson(
            route('projects.tasks.dependencies.store', [$this->project, $this->taskA]),
            ['predecessor_id' => $this->taskA->id]
        );

        $response->assertStatus(422);
        $response->assertJsonPath('error', fn ($v) => str_contains($v, 'elle-même'));
    }

    // ── Contrainte inter-projet ───────────────────────────────────────────

    public function test_dependance_inter_projet_refusee(): void
    {
        $otherProject = Project::factory()->create([
            'created_by' => $this->owner->id,
            'name' => 'Autre projet',
            'start_date' => '2026-01-01',
            'due_date' => '2026-12-31',
        ]);

        $taskOther = Task::on('tenant')->create([
            'project_id' => $otherProject->id,
            'created_by' => $this->owner->id,
            'title' => 'Tâche autre projet',
            'status' => 'todo',
            'priority' => 'low',
            'start_date' => '2026-01-01',
            'due_date' => '2026-03-31',
        ]);

        $this->actingAs($this->owner);

        // Tenter d'ajouter une dépendance vers une tâche d'un autre projet
        $response = $this->postJson(
            route('projects.tasks.dependencies.store', [$this->project, $this->taskB]),
            ['predecessor_id' => $taskOther->id]
        );

        $response->assertStatus(404);
    }

    // ── Suppression ───────────────────────────────────────────────────────

    public function test_suppression_dependance(): void
    {
        DB::connection('tenant')->table('task_dependencies')->insert([
            'task_id' => $this->taskB->id,
            'depends_on_task_id' => $this->taskA->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->owner);

        $this->deleteJson(
            route('projects.tasks.dependencies.destroy', [$this->project, $this->taskB, $this->taskA])
        )->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseMissing('task_dependencies', [
            'task_id' => $this->taskB->id,
            'depends_on_task_id' => $this->taskA->id,
        ], 'tenant');
    }

    // ── Réponse JSON show() ───────────────────────────────────────────────

    public function test_show_inclut_les_predecesseurs(): void
    {
        $this->taskA->update(['status' => 'in_progress']);

        DB::connection('tenant')->table('task_dependencies')->insert([
            'task_id' => $this->taskB->id,
            'depends_on_task_id' => $this->taskA->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->owner);

        $response = $this->getJson(
            route('projects.tasks.show', [$this->project, $this->taskB])
        );

        $response->assertOk();
        $predecessors = $response->json('task.predecessors');
        $this->assertCount(1, $predecessors);
        $this->assertSame($this->taskA->id, $predecessors[0]['id']);
        $this->assertSame('A — Marché public', $predecessors[0]['title']);
        $this->assertSame('in_progress', $predecessors[0]['status']);
    }

    public function test_show_predecesseurs_vide_si_aucune_dependance(): void
    {
        $this->actingAs($this->owner);

        $response = $this->getJson(
            route('projects.tasks.show', [$this->project, $this->taskA])
        );

        $response->assertOk();
        $this->assertEmpty($response->json('task.predecessors'));
    }
}
