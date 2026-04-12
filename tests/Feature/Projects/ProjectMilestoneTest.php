<?php

namespace Tests\Feature\Projects;

use App\Models\Tenant\Project;
use App\Models\Tenant\ProjectMember;
use App\Models\Tenant\ProjectMilestone;
use App\Models\Tenant\User;
use Tests\TestCase;

/**
 * Tests des jalons et phases de projet.
 * Couvre : jalons autonomes, phases (parent_id null), jalons enfants (parent_id non null).
 */
class ProjectMilestoneTest extends TestCase
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
        ProjectMember::create(['project_id' => $this->project->id, 'user_id' => $this->owner->id,  'role' => 'owner']);
        ProjectMember::create(['project_id' => $this->project->id, 'user_id' => $this->member->id, 'role' => 'member']);
    }

    // ── Jalons autonomes ──────────────────────────────────────────────────────

    public function test_owner_peut_creer_un_jalon(): void
    {
        $this->actingAs($this->owner);

        $this->post(route('projects.milestones.store', $this->project), [
            'title' => 'Livraison v1.0',
            'due_date' => '2026-06-30',
            'color' => '#EA580C',
        ])->assertRedirect();

        $this->assertDatabaseHas('project_milestones', [
            'project_id' => $this->project->id,
            'title' => 'Livraison v1.0',
            'parent_id' => null,
        ], 'tenant');
    }

    public function test_member_ne_peut_pas_creer_un_jalon(): void
    {
        $this->actingAs($this->member);
        $this->post(route('projects.milestones.store', $this->project), [
            'title' => 'Jalon interdit',
            'due_date' => '2026-06-30',
        ])->assertForbidden();
    }

    public function test_jalon_sans_date_est_refuse(): void
    {
        $this->actingAs($this->owner);
        $this->post(route('projects.milestones.store', $this->project), [
            'title' => 'Sans date',
        ])->assertSessionHasErrors('due_date');
    }

    public function test_owner_peut_marquer_un_jalon_comme_atteint(): void
    {
        $milestone = ProjectMilestone::on('tenant')->create([
            'project_id' => $this->project->id,
            'title' => 'Jalon test',
            'due_date' => '2026-06-30',
            'color' => '#EA580C',
        ]);

        $this->actingAs($this->owner);
        $this->patch(route('projects.milestones.update', [$this->project, $milestone]), [
            'reached' => true,
        ])->assertRedirect();

        $this->assertNotNull(ProjectMilestone::on('tenant')->find($milestone->id)->reached_at);
    }

    public function test_owner_peut_supprimer_un_jalon(): void
    {
        $milestone = ProjectMilestone::on('tenant')->create([
            'project_id' => $this->project->id,
            'title' => 'À supprimer',
            'due_date' => '2026-12-31',
        ]);

        $this->actingAs($this->owner);
        $this->delete(route('projects.milestones.destroy', [$this->project, $milestone]))->assertRedirect();
        $this->assertSoftDeleted('project_milestones', ['id' => $milestone->id], 'tenant');
    }

    // ── Phases ────────────────────────────────────────────────────────────────

    public function test_owner_peut_creer_une_phase(): void
    {
        $this->actingAs($this->owner);

        $this->post(route('projects.milestones.store', $this->project), [
            'title' => 'Phase 1 — Socle technique',
            'node_type' => 'Phase',
            'start_date' => '2026-01-01',
            'due_date' => '2026-03-31',
            'color' => '#1E3A5F',
        ])->assertRedirect();

        $this->assertDatabaseHas('project_milestones', [
            'project_id' => $this->project->id,
            'title' => 'Phase 1 — Socle technique',
            'node_type' => 'Phase',
            'parent_id' => null,
        ], 'tenant');
    }

    public function test_noeud_sans_date_de_fin_est_refuse(): void
    {
        $this->actingAs($this->owner);
        $this->post(route('projects.milestones.store', $this->project), [
            'title' => 'Nœud sans date',
        ])->assertSessionHasErrors('due_date');
    }

    public function test_member_ne_peut_pas_creer_un_noeud(): void
    {
        $this->actingAs($this->member);
        $this->post(route('projects.milestones.store', $this->project), [
            'title' => 'Nœud interdit',
            'due_date' => '2026-06-30',
        ])->assertForbidden();
    }

    // ── Jalons enfants (rattachés à une phase) ────────────────────────────────

    public function test_owner_peut_creer_un_jalon_enfant_dans_une_phase(): void
    {
        $phase = ProjectMilestone::on('tenant')->create([
            'project_id' => $this->project->id,
            'title' => 'Phase 1',
            'due_date' => '2026-03-31',
            'parent_id' => null,
        ]);

        $this->actingAs($this->owner);
        $this->post(route('projects.milestones.store', $this->project), [
            'title' => 'CI/CD vert',
            'due_date' => '2026-02-15',
            'parent_id' => $phase->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('project_milestones', [
            'project_id' => $this->project->id,
            'title' => 'CI/CD vert',
            'parent_id' => $phase->id,
        ], 'tenant');
    }

    public function test_parent_id_appartenant_a_un_autre_projet_est_refuse(): void
    {
        $otherProject = Project::factory()->create(['created_by' => $this->owner->id]);
        ProjectMember::create(['project_id' => $otherProject->id, 'user_id' => $this->owner->id, 'role' => 'owner']);

        $foreignPhase = ProjectMilestone::on('tenant')->create([
            'project_id' => $otherProject->id,
            'title' => 'Phase autre projet',
            'due_date' => '2026-12-31',
            'parent_id' => null,
        ]);

        $this->actingAs($this->owner);
        $this->post(route('projects.milestones.store', $this->project), [
            'title' => 'Injection cross-project',
            'due_date' => '2026-06-30',
            'parent_id' => $foreignPhase->id,
        ])->assertStatus(422);
    }

    public function test_suppression_phase_supprime_aussi_les_jalons_enfants(): void
    {
        $phase = ProjectMilestone::on('tenant')->create([
            'project_id' => $this->project->id,
            'title' => 'Phase à supprimer',
            'due_date' => '2026-06-30',
            'parent_id' => null,
        ]);

        $child = ProjectMilestone::on('tenant')->create([
            'project_id' => $this->project->id,
            'title' => 'Jalon enfant',
            'due_date' => '2026-05-30',
            'parent_id' => $phase->id,
        ]);

        $this->actingAs($this->owner);
        $this->delete(route('projects.milestones.destroy', [$this->project, $phase]))->assertRedirect();

        $this->assertSoftDeleted('project_milestones', ['id' => $phase->id], 'tenant');
        $this->assertSoftDeleted('project_milestones', ['id' => $child->id], 'tenant');
    }

    public function test_is_root_retourne_true_sans_parent_id(): void
    {
        $root = ProjectMilestone::on('tenant')->create([
            'project_id' => $this->project->id,
            'title' => 'Phase',
            'node_type' => 'Phase',
            'due_date' => '2026-12-31',
            'parent_id' => null,
        ]);

        $this->assertTrue($root->isRoot());
        $this->assertTrue($root->isPhase()); // alias
        $this->assertFalse($root->isChild());
        $this->assertEquals(0, $root->depth());
    }

    public function test_is_child_retourne_true_avec_parent_id(): void
    {
        $root = ProjectMilestone::on('tenant')->create([
            'project_id' => $this->project->id,
            'title' => 'Phase parente',
            'due_date' => '2026-12-31',
            'parent_id' => null,
        ]);

        $child = ProjectMilestone::on('tenant')->create([
            'project_id' => $this->project->id,
            'title' => 'Enfant',
            'due_date' => '2026-11-30',
            'parent_id' => $root->id,
        ]);

        $this->assertFalse($child->isRoot());
        $this->assertTrue($child->isChild());
        $this->assertEquals(1, $child->depth());
        $this->assertEquals($root->id, $child->parent_id);
    }

    public function test_profondeur_maximale_est_refusee(): void
    {
        $this->actingAs($this->owner);

        // Créer une chaîne de MAX_DEPTH+1 nœuds (depth 0 à MAX_DEPTH)
        $current = null;
        for ($i = 0; $i <= ProjectMilestone::MAX_DEPTH; $i++) {
            $current = ProjectMilestone::on('tenant')->create([
                'project_id' => $this->project->id,
                'title' => "Niveau $i",
                'due_date' => '2026-12-31',
                'parent_id' => $current?->id,
            ]);
        }

        // Le nœud à MAX_DEPTH est à profondeur MAX_DEPTH, donc refusé
        $this->post(route('projects.milestones.store', $this->project), [
            'title' => 'Trop profond',
            'due_date' => '2026-12-31',
            'parent_id' => $current->id,
        ])->assertStatus(422);
    }

    // ── Guard nœud terminé ───────────────────────────────────────────────────

    public function test_phase_avec_jalons_non_atteints_ne_peut_pas_être_terminée(): void
    {
        $phase = ProjectMilestone::on('tenant')->create([
            'project_id' => $this->project->id,
            'title' => 'Phase A',
            'due_date' => '2026-12-31',
            'parent_id' => null,
        ]);

        // Jalon enfant non atteint
        ProjectMilestone::on('tenant')->create([
            'project_id' => $this->project->id,
            'title' => 'Jalon en cours',
            'due_date' => '2026-11-30',
            'parent_id' => $phase->id,
        ]);

        $this->actingAs($this->owner);
        $this->patch(route('projects.milestones.update', [$this->project, $phase]), [
            'reached' => true,
        ])->assertSessionHasErrors('reached');

        $this->assertNull(ProjectMilestone::on('tenant')->find($phase->id)->reached_at);
    }

    public function test_phase_peut_être_terminée_quand_tous_les_jalons_sont_atteints(): void
    {
        $phase = ProjectMilestone::on('tenant')->create([
            'project_id' => $this->project->id,
            'title' => 'Phase B',
            'due_date' => '2026-12-31',
            'parent_id' => null,
        ]);

        // Jalon enfant atteint
        ProjectMilestone::on('tenant')->create([
            'project_id' => $this->project->id,
            'title' => 'Jalon atteint',
            'due_date' => '2026-11-30',
            'parent_id' => $phase->id,
            'reached_at' => now(),
        ]);

        $this->actingAs($this->owner);
        $this->patch(route('projects.milestones.update', [$this->project, $phase]), [
            'reached' => true,
        ])->assertRedirect();

        $this->assertNotNull(ProjectMilestone::on('tenant')->find($phase->id)->reached_at);
    }
}
