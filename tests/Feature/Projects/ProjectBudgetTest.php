<?php

namespace Tests\Feature\Projects;

use App\Models\Tenant\Project;
use App\Models\Tenant\ProjectBudget;
use App\Models\Tenant\ProjectMember;
use App\Models\Tenant\User;
use Tests\TestCase;

/**
 * Tests des enveloppes budgétaires d'un projet.
 * Policy : manageBudget = owner uniquement (+ Admin/DGS via before()).
 */
class ProjectBudgetTest extends TestCase
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

        $this->project = Project::factory()->create(['created_by' => $this->owner->id]);
        ProjectMember::create(['project_id' => $this->project->id, 'user_id' => $this->owner->id,  'role' => 'owner']);
        ProjectMember::create(['project_id' => $this->project->id, 'user_id' => $this->member->id, 'role' => 'member']);
        ProjectMember::create(['project_id' => $this->project->id, 'user_id' => $this->viewer->id, 'role' => 'viewer']);
    }

    // ── store ─────────────────────────────────────────────────────────────────

    public function test_owner_peut_ajouter_une_ligne_budgetaire(): void
    {
        $this->actingAs($this->owner);

        $response = $this->post(route('projects.budgets.store', $this->project), [
            'type' => 'invest',
            'label' => 'Travaux bâtiment',
            'year' => 2026,
            'amount_planned' => 50000,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('project_budgets', [
            'project_id' => $this->project->id,
            'label' => 'Travaux bâtiment',
            'type' => 'invest',
            'amount_planned' => 50000,
        ], 'tenant');
    }

    public function test_member_ne_peut_pas_ajouter_une_ligne_budgetaire(): void
    {
        $this->actingAs($this->member);

        $this->post(route('projects.budgets.store', $this->project), [
            'type' => 'fonct',
            'label' => 'Fournitures',
            'year' => 2026,
            'amount_planned' => 1000,
        ])->assertForbidden();
    }

    public function test_viewer_ne_peut_pas_ajouter_une_ligne_budgetaire(): void
    {
        $this->actingAs($this->viewer);

        $this->post(route('projects.budgets.store', $this->project), [
            'type' => 'fonct',
            'label' => 'Tentative',
            'year' => 2026,
            'amount_planned' => 500,
        ])->assertForbidden();
    }

    // ── update ────────────────────────────────────────────────────────────────

    public function test_owner_peut_modifier_une_ligne_budgetaire(): void
    {
        $budget = ProjectBudget::on('tenant')->create([
            'project_id' => $this->project->id,
            'type' => 'invest',
            'label' => 'Ancienne ligne',
            'year' => 2026,
            'amount_planned' => 10000,
            'amount_committed' => 0,
            'amount_paid' => 0,
            'created_by' => $this->owner->id,
        ]);

        $this->actingAs($this->owner);

        $this->patch(route('projects.budgets.update', [$this->project, $budget]), [
            'amount_committed' => 8000,
        ])->assertRedirect();

        $this->assertDatabaseHas('project_budgets', [
            'id' => $budget->id,
            'amount_committed' => 8000,
        ], 'tenant');
    }

    public function test_member_ne_peut_pas_modifier_une_ligne_budgetaire(): void
    {
        $budget = ProjectBudget::on('tenant')->create([
            'project_id' => $this->project->id,
            'type' => 'fonct',
            'label' => 'Ligne protégée',
            'year' => 2026,
            'amount_planned' => 5000,
            'amount_committed' => 0,
            'amount_paid' => 0,
            'created_by' => $this->owner->id,
        ]);

        $this->actingAs($this->member);

        $this->patch(route('projects.budgets.update', [$this->project, $budget]), [
            'amount_committed' => 9999,
        ])->assertForbidden();
    }

    // ── destroy ───────────────────────────────────────────────────────────────

    public function test_owner_peut_supprimer_une_ligne_budgetaire(): void
    {
        $budget = ProjectBudget::on('tenant')->create([
            'project_id' => $this->project->id,
            'type' => 'invest',
            'label' => 'À supprimer',
            'year' => 2026,
            'amount_planned' => 1000,
            'amount_committed' => 0,
            'amount_paid' => 0,
            'created_by' => $this->owner->id,
        ]);

        $this->actingAs($this->owner);

        $this->delete(route('projects.budgets.destroy', [$this->project, $budget]))->assertRedirect();

        $this->assertSoftDeleted('project_budgets', ['id' => $budget->id], 'tenant');
    }

    public function test_admin_peut_gerer_le_budget_via_before(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $this->actingAs($admin);

        $this->post(route('projects.budgets.store', $this->project), [
            'type' => 'fonct',
            'label' => 'Ligne admin',
            'year' => 2026,
            'amount_planned' => 2000,
        ])->assertRedirect();
    }
}
