<?php

namespace Tests\Feature\Projects;

use App\Models\Tenant\Project;
use App\Models\Tenant\ProjectBudget;
use App\Models\Tenant\ProjectMember;
use App\Models\Tenant\User;
use Tests\TestCase;

/**
 * Tests des enveloppes budgétaires d'un projet.
 *
 * Policy : manageBudget = owner uniquement (+ Admin/DGS/Président via before()).
 *
 * Couverture :
 *  - store  : owner ✓, member ✗, viewer ✗, outsider ✗, admin ✓ (before), dgs ✓ (before)
 *  - update : owner ✓, member ✗, viewer ✗, outsider ✗, mauvais projet → 404
 *  - destroy: owner ✓, viewer ✗, outsider ✗, mauvais projet → 404, soft-delete vérifié
 *  - validation : type invalide, montant négatif, année hors plage, label manquant
 *  - co-financement : taux stocké correctement
 *  - JSON : réponse JSON sur wantsJson() (store, update, destroy)
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

    // ── Helper ────────────────────────────────────────────────────────────────

    private function makeBudget(array $overrides = []): ProjectBudget
    {
        return ProjectBudget::on('tenant')->create(array_merge([
            'project_id' => $this->project->id,
            'type' => 'invest',
            'label' => 'Ligne test',
            'year' => 2026,
            'amount_planned' => 10000,
            'amount_committed' => 0,
            'amount_paid' => 0,
            'created_by' => $this->owner->id,
        ], $overrides));
    }

    // ══════════════════════════════════════════════════════════════════════════
    // STORE
    // ══════════════════════════════════════════════════════════════════════════

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

    public function test_owner_peut_ajouter_une_ligne_avec_cofinancement(): void
    {
        $this->actingAs($this->owner);

        $this->post(route('projects.budgets.store', $this->project), [
            'type' => 'invest',
            'label' => 'Subvention DETR',
            'year' => 2026,
            'amount_planned' => 80000,
            'cofinancer' => 'DETR',
            'cofinancing_rate' => 40,
        ])->assertRedirect();

        $this->assertDatabaseHas('project_budgets', [
            'project_id' => $this->project->id,
            'cofinancer' => 'DETR',
            'cofinancing_rate' => 40,
        ], 'tenant');
    }

    public function test_store_retourne_json_si_wants_json(): void
    {
        $this->actingAs($this->owner);

        $this->postJson(route('projects.budgets.store', $this->project), [
            'type' => 'fonct',
            'label' => 'Ligne JSON',
            'year' => 2026,
            'amount_planned' => 3000,
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'budget' => ['id', 'label', 'amount_planned']]);
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
            'label' => 'Tentative viewer',
            'year' => 2026,
            'amount_planned' => 500,
        ])->assertForbidden();
    }

    public function test_outsider_ne_peut_pas_ajouter_une_ligne_budgetaire(): void
    {
        $this->actingAs($this->outsider);

        $this->post(route('projects.budgets.store', $this->project), [
            'type' => 'invest',
            'label' => 'Tentative outsider',
            'year' => 2026,
            'amount_planned' => 1000,
        ])->assertForbidden();
    }

    // ── Validation store ──────────────────────────────────────────────────────

    public function test_store_rejette_type_invalide(): void
    {
        $this->actingAs($this->owner);

        $this->post(route('projects.budgets.store', $this->project), [
            'type' => 'mauvais_type',
            'label' => 'Test',
            'year' => 2026,
            'amount_planned' => 1000,
        ])->assertSessionHasErrors('type');
    }

    public function test_store_rejette_montant_negatif(): void
    {
        $this->actingAs($this->owner);

        $this->post(route('projects.budgets.store', $this->project), [
            'type' => 'invest',
            'label' => 'Test',
            'year' => 2026,
            'amount_planned' => -500,
        ])->assertSessionHasErrors('amount_planned');
    }

    public function test_store_rejette_annee_hors_plage(): void
    {
        $this->actingAs($this->owner);

        $this->post(route('projects.budgets.store', $this->project), [
            'type' => 'invest',
            'label' => 'Test',
            'year' => 1999,
            'amount_planned' => 1000,
        ])->assertSessionHasErrors('year');
    }

    public function test_store_rejette_label_manquant(): void
    {
        $this->actingAs($this->owner);

        $this->post(route('projects.budgets.store', $this->project), [
            'type' => 'invest',
            'year' => 2026,
            'amount_planned' => 1000,
        ])->assertSessionHasErrors('label');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // UPDATE
    // ══════════════════════════════════════════════════════════════════════════

    public function test_owner_peut_modifier_une_ligne_budgetaire(): void
    {
        $budget = $this->makeBudget(['label' => 'Ancienne ligne', 'amount_planned' => 10000]);

        $this->actingAs($this->owner);

        $this->patch(route('projects.budgets.update', [$this->project, $budget]), [
            'amount_committed' => 8000,
        ])->assertRedirect();

        $this->assertDatabaseHas('project_budgets', [
            'id' => $budget->id,
            'amount_committed' => 8000,
        ], 'tenant');
    }

    public function test_owner_peut_modifier_le_label_et_le_type(): void
    {
        $budget = $this->makeBudget(['label' => 'Label original', 'type' => 'invest']);

        $this->actingAs($this->owner);

        $this->patch(route('projects.budgets.update', [$this->project, $budget]), [
            'label' => 'Label modifié',
            'type' => 'fonct',
        ])->assertRedirect();

        $this->assertDatabaseHas('project_budgets', [
            'id' => $budget->id,
            'label' => 'Label modifié',
            'type' => 'fonct',
        ], 'tenant');
    }

    public function test_owner_peut_modifier_le_cofinancement(): void
    {
        $budget = $this->makeBudget();

        $this->actingAs($this->owner);

        $this->patch(route('projects.budgets.update', [$this->project, $budget]), [
            'cofinancer' => 'Région Pays de la Loire',
            'cofinancing_rate' => 30,
        ])->assertRedirect();

        $this->assertDatabaseHas('project_budgets', [
            'id' => $budget->id,
            'cofinancer' => 'Région Pays de la Loire',
            'cofinancing_rate' => 30,
        ], 'tenant');
    }

    public function test_update_retourne_json_si_wants_json(): void
    {
        $budget = $this->makeBudget();

        $this->actingAs($this->owner);

        $this->patchJson(route('projects.budgets.update', [$this->project, $budget]), [
            'amount_committed' => 5000,
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'budget' => ['id', 'amount_committed']]);
    }

    public function test_member_ne_peut_pas_modifier_une_ligne_budgetaire(): void
    {
        $budget = $this->makeBudget(['label' => 'Ligne protégée']);

        $this->actingAs($this->member);

        $this->patch(route('projects.budgets.update', [$this->project, $budget]), [
            'amount_committed' => 9999,
        ])->assertForbidden();
    }

    public function test_viewer_ne_peut_pas_modifier_une_ligne_budgetaire(): void
    {
        $budget = $this->makeBudget();

        $this->actingAs($this->viewer);

        $this->patch(route('projects.budgets.update', [$this->project, $budget]), [
            'amount_committed' => 1,
        ])->assertForbidden();
    }

    public function test_outsider_ne_peut_pas_modifier_une_ligne_budgetaire(): void
    {
        $budget = $this->makeBudget();

        $this->actingAs($this->outsider);

        $this->patch(route('projects.budgets.update', [$this->project, $budget]), [
            'amount_committed' => 1,
        ])->assertForbidden();
    }

    public function test_update_refuse_budget_appartenant_a_un_autre_projet(): void
    {
        $autreProjet = Project::factory()->create(['created_by' => $this->owner->id]);
        ProjectMember::create(['project_id' => $autreProjet->id, 'user_id' => $this->owner->id, 'role' => 'owner']);

        $budgetAutreProjet = ProjectBudget::on('tenant')->create([
            'project_id' => $autreProjet->id,
            'type' => 'invest',
            'label' => 'Budget autre projet',
            'year' => 2026,
            'amount_planned' => 5000,
            'amount_committed' => 0,
            'amount_paid' => 0,
            'created_by' => $this->owner->id,
        ]);

        $this->actingAs($this->owner);

        $this->patch(
            route('projects.budgets.update', [$this->project, $budgetAutreProjet]),
            ['amount_committed' => 999]
        )->assertNotFound();
    }

    // ── Validation update ─────────────────────────────────────────────────────

    public function test_update_rejette_montant_engage_negatif(): void
    {
        $budget = $this->makeBudget();

        $this->actingAs($this->owner);

        $this->patch(route('projects.budgets.update', [$this->project, $budget]), [
            'amount_committed' => -100,
        ])->assertSessionHasErrors('amount_committed');
    }

    public function test_update_rejette_taux_cofinancement_superieur_a_100(): void
    {
        $budget = $this->makeBudget();

        $this->actingAs($this->owner);

        $this->patch(route('projects.budgets.update', [$this->project, $budget]), [
            'cofinancing_rate' => 150,
        ])->assertSessionHasErrors('cofinancing_rate');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // DESTROY
    // ══════════════════════════════════════════════════════════════════════════

    public function test_owner_peut_supprimer_une_ligne_budgetaire(): void
    {
        $budget = $this->makeBudget(['label' => 'À supprimer']);

        $this->actingAs($this->owner);

        $this->delete(route('projects.budgets.destroy', [$this->project, $budget]))
            ->assertRedirect();

        $this->assertSoftDeleted('project_budgets', ['id' => $budget->id], 'tenant');
    }

    public function test_destroy_retourne_json_si_wants_json(): void
    {
        $budget = $this->makeBudget(['label' => 'À supprimer JSON']);

        $this->actingAs($this->owner);

        $this->deleteJson(route('projects.budgets.destroy', [$this->project, $budget]))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('project_budgets', ['id' => $budget->id], 'tenant');
    }

    public function test_viewer_ne_peut_pas_supprimer_une_ligne_budgetaire(): void
    {
        $budget = $this->makeBudget();

        $this->actingAs($this->viewer);

        $this->delete(route('projects.budgets.destroy', [$this->project, $budget]))
            ->assertForbidden();

        $this->assertDatabaseHas('project_budgets', ['id' => $budget->id, 'deleted_at' => null], 'tenant');
    }

    public function test_outsider_ne_peut_pas_supprimer_une_ligne_budgetaire(): void
    {
        $budget = $this->makeBudget();

        $this->actingAs($this->outsider);

        $this->delete(route('projects.budgets.destroy', [$this->project, $budget]))
            ->assertForbidden();
    }

    public function test_destroy_refuse_budget_appartenant_a_un_autre_projet(): void
    {
        $autreProjet = Project::factory()->create(['created_by' => $this->owner->id]);
        ProjectMember::create(['project_id' => $autreProjet->id, 'user_id' => $this->owner->id, 'role' => 'owner']);

        $budgetAutreProjet = ProjectBudget::on('tenant')->create([
            'project_id' => $autreProjet->id,
            'type' => 'fonct',
            'label' => 'Budget orphelin',
            'year' => 2026,
            'amount_planned' => 1000,
            'amount_committed' => 0,
            'amount_paid' => 0,
            'created_by' => $this->owner->id,
        ]);

        $this->actingAs($this->owner);

        $this->delete(route('projects.budgets.destroy', [$this->project, $budgetAutreProjet]))
            ->assertNotFound();
    }

    // ══════════════════════════════════════════════════════════════════════════
    // ADMIN / DGS via before()
    // ══════════════════════════════════════════════════════════════════════════

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

    public function test_dgs_peut_gerer_le_budget_via_before(): void
    {
        $dgs = User::factory()->create(['role' => 'dgs', 'status' => 'active']);

        $this->actingAs($dgs);

        $this->post(route('projects.budgets.store', $this->project), [
            'type' => 'invest',
            'label' => 'Ligne DGS',
            'year' => 2026,
            'amount_planned' => 15000,
        ])->assertRedirect();
    }
}
