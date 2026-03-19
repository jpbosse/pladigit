<?php

namespace Tests\Feature\Projects;

use App\Models\Tenant\Project;
use App\Models\Tenant\ProjectMember;
use App\Models\Tenant\User;
use Tests\TestCase;

/**
 * Tests de la politique d'accès (ProjectPolicy).
 * Vérifie les 3 couches : Admin/Président/DGS, owner, member, viewer, non-membre.
 * Inclut les 3 méthodes ajoutées en Phase 8 : manageBudget, manageStakeholders, manageChange.
 */
class ProjectPolicyTest extends TestCase
{
    private User $admin;

    private User $dgs;

    private User $owner;

    private User $member;

    private User $viewer;

    private User $outsider;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin',          'status' => 'active']);
        $this->dgs = User::factory()->create(['role' => 'dgs',            'status' => 'active']);
        $this->owner = User::factory()->create(['role' => 'resp_direction',  'status' => 'active']);
        $this->member = User::factory()->create(['role' => 'user',           'status' => 'active']);
        $this->viewer = User::factory()->create(['role' => 'user',           'status' => 'active']);
        $this->outsider = User::factory()->create(['role' => 'user',           'status' => 'active']);

        $this->project = Project::factory()->create(['created_by' => $this->owner->id]);
        ProjectMember::create(['project_id' => $this->project->id, 'user_id' => $this->owner->id,  'role' => 'owner']);
        ProjectMember::create(['project_id' => $this->project->id, 'user_id' => $this->member->id, 'role' => 'member']);
        ProjectMember::create(['project_id' => $this->project->id, 'user_id' => $this->viewer->id, 'role' => 'viewer']);
    }

    // ── view ──────────────────────────────────────────────────────────────────

    public function test_admin_peut_voir_tous_les_projets(): void
    {
        $this->actingAs($this->admin);
        $this->get(route('projects.show', $this->project))->assertOk();
    }

    public function test_dgs_peut_voir_le_projet(): void
    {
        $this->actingAs($this->dgs);
        $this->get(route('projects.show', $this->project))->assertOk();
    }

    public function test_viewer_peut_voir_le_projet(): void
    {
        $this->actingAs($this->viewer);
        $this->get(route('projects.show', $this->project))->assertOk();
    }

    public function test_non_membre_ne_peut_pas_voir_le_projet(): void
    {
        $this->actingAs($this->outsider);
        $this->get(route('projects.show', $this->project))->assertForbidden();
    }

    // ── update ────────────────────────────────────────────────────────────────

    public function test_admin_peut_modifier_tout_projet(): void
    {
        $this->actingAs($this->admin);
        $this->put(route('projects.update', $this->project), [
            'name' => 'Modifié admin',
            'status' => 'active',
        ])->assertRedirect();
    }

    public function test_viewer_ne_peut_pas_modifier_le_projet(): void
    {
        $this->actingAs($this->viewer);
        $this->put(route('projects.update', $this->project), [
            'name' => 'Tentative',
            'status' => 'active',
        ])->assertForbidden();
    }

    public function test_non_membre_obtient_403_sur_modification(): void
    {
        $this->actingAs($this->outsider);
        $this->put(route('projects.update', $this->project), [
            'name' => 'Hack',
            'status' => 'active',
        ])->assertForbidden();
    }

    // ── manageBudget : owner uniquement (+ Admin/DGS via before()) ────────────

    public function test_owner_peut_gerer_le_budget(): void
    {
        $this->actingAs($this->owner);
        $this->post(route('projects.budgets.store', $this->project), [
            'type' => 'invest',
            'label' => 'Test policy budget',
            'year' => 2026,
            'amount_planned' => 10000,
        ])->assertRedirect();
    }

    public function test_member_ne_peut_pas_gerer_le_budget(): void
    {
        $this->actingAs($this->member);
        $this->post(route('projects.budgets.store', $this->project), [
            'type' => 'invest',
            'label' => 'Tentative member',
            'year' => 2026,
            'amount_planned' => 1000,
        ])->assertForbidden();
    }

    public function test_dgs_peut_gerer_le_budget_via_before(): void
    {
        $this->actingAs($this->dgs);
        $this->post(route('projects.budgets.store', $this->project), [
            'type' => 'fonct',
            'label' => 'DGS override',
            'year' => 2026,
            'amount_planned' => 5000,
        ])->assertRedirect();
    }

    // ── manageStakeholders : owner uniquement (+ Admin/DGS via before()) ──────

    public function test_owner_peut_gerer_les_parties_prenantes(): void
    {
        $this->actingAs($this->owner);
        $this->post(route('projects.stakeholders.store', $this->project), [
            'name' => 'Test policy stakeholder',
            'role' => 'Partenaire',
            'adhesion' => 'neutre',
            'influence' => 'medium',
        ])->assertRedirect();
    }

    public function test_member_ne_peut_pas_gerer_les_parties_prenantes(): void
    {
        $this->actingAs($this->member);
        $this->post(route('projects.stakeholders.store', $this->project), [
            'name' => 'Tentative member',
            'role' => 'Test',
            'adhesion' => 'champion',
            'influence' => 'low',
        ])->assertForbidden();
    }

    public function test_admin_peut_gerer_les_parties_prenantes_via_before(): void
    {
        $this->actingAs($this->admin);
        $this->post(route('projects.stakeholders.store', $this->project), [
            'name' => 'Admin override',
            'role' => 'Superviseur',
            'adhesion' => 'supporter',
            'influence' => 'high',
        ])->assertRedirect();
    }

    // ── manageChange : owner ET member (viewer = 403) ─────────────────────────

    public function test_owner_peut_gerer_la_conduite_du_changement(): void
    {
        $this->actingAs($this->owner);
        $this->post(route('projects.risks.store', $this->project), [
            'title' => 'Risque policy test owner',
            'category' => 'humain',
            'probability' => 'low',
            'impact' => 'low',
        ])->assertRedirect();
    }

    public function test_member_peut_gerer_la_conduite_du_changement(): void
    {
        $this->actingAs($this->member);
        $this->post(route('projects.risks.store', $this->project), [
            'title' => 'Risque identifié par member',
            'category' => 'technique',
            'probability' => 'medium',
            'impact' => 'medium',
        ])->assertRedirect();
    }

    public function test_viewer_ne_peut_pas_gerer_la_conduite_du_changement(): void
    {
        $this->actingAs($this->viewer);
        $this->post(route('projects.risks.store', $this->project), [
            'title' => 'Tentative viewer',
            'category' => 'autre',
            'probability' => 'low',
            'impact' => 'low',
        ])->assertForbidden();
    }

    // ── ADR-011 : Hiérarchie organisationnelle ────────────────────────────────

    public function test_resp_direction_peut_voir_projet_de_sa_direction(): void
    {
        // Créer une direction, y rattacher le member comme agent et le resp comme manager
        $direction = \App\Models\Tenant\Department::factory()->direction()->create();
        $respDir = User::factory()->create(['role' => 'resp_direction', 'status' => 'active']);

        // Rattacher resp comme manager de la direction
        \Illuminate\Support\Facades\DB::connection('tenant')->table('user_department')->insert([
            'user_id' => $respDir->id,
            'department_id' => $direction->id,
            'is_manager' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Rattacher le member du projet à cette direction
        \Illuminate\Support\Facades\DB::connection('tenant')->table('user_department')->insert([
            'user_id' => $this->member->id,
            'department_id' => $direction->id,
            'is_manager' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Le resp de direction n'est pas membre du projet, mais un membre l'est
        $this->actingAs($respDir);
        $this->get(route('projects.show', $this->project))->assertOk();
    }

    public function test_resp_direction_ne_peut_pas_modifier_projet_hors_membre(): void
    {
        $direction = \App\Models\Tenant\Department::factory()->direction()->create();
        $respDir = User::factory()->create(['role' => 'resp_direction', 'status' => 'active']);

        \Illuminate\Support\Facades\DB::connection('tenant')->table('user_department')->insert([
            'user_id' => $respDir->id,
            'department_id' => $direction->id,
            'is_manager' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \Illuminate\Support\Facades\DB::connection('tenant')->table('user_department')->insert([
            'user_id' => $this->member->id,
            'department_id' => $direction->id,
            'is_manager' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Peut voir, mais pas modifier (pas owner du projet)
        $this->actingAs($respDir);
        $this->put(route('projects.update', $this->project), [
            'name' => 'Tentative resp direction',
            'status' => 'active',
        ])->assertForbidden();
    }

    public function test_resp_service_peut_voir_projet_de_son_service(): void
    {
        $direction = \App\Models\Tenant\Department::factory()->direction()->create();
        $service = \App\Models\Tenant\Department::factory()->service($direction->id)->create();
        $respSvc = User::factory()->create(['role' => 'resp_service', 'status' => 'active']);

        // Resp rattaché comme manager du service
        \Illuminate\Support\Facades\DB::connection('tenant')->table('user_department')->insert([
            'user_id' => $respSvc->id,
            'department_id' => $service->id,
            'is_manager' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Member du projet rattaché au même service
        \Illuminate\Support\Facades\DB::connection('tenant')->table('user_department')->insert([
            'user_id' => $this->member->id,
            'department_id' => $service->id,
            'is_manager' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($respSvc);
        $this->get(route('projects.show', $this->project))->assertOk();
    }

    public function test_resp_service_hors_perimetre_ne_peut_pas_voir(): void
    {
        // Un resp_service dont aucun subordonné n'est membre du projet
        $autreDir = \App\Models\Tenant\Department::factory()->direction()->create();
        $autreSvc = \App\Models\Tenant\Department::factory()->service($autreDir->id)->create();
        $respHors = User::factory()->create(['role' => 'resp_service', 'status' => 'active']);

        \Illuminate\Support\Facades\DB::connection('tenant')->table('user_department')->insert([
            'user_id' => $respHors->id,
            'department_id' => $autreSvc->id,
            'is_manager' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Aucun membre du projet n'est dans ce service → 403
        $this->actingAs($respHors);
        $this->get(route('projects.show', $this->project))->assertForbidden();
    }

    // ── Projet privé ──────────────────────────────────────────────────────────

    public function test_projet_prive_invisible_pour_resp_direction_hors_membre(): void
    {
        $direction = \App\Models\Tenant\Department::factory()->direction()->create();
        $respDir = User::factory()->create(['role' => 'resp_direction', 'status' => 'active']);

        \Illuminate\Support\Facades\DB::connection('tenant')->table('user_department')->insert([
            'user_id' => $respDir->id, 'department_id' => $direction->id,
            'is_manager' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        \Illuminate\Support\Facades\DB::connection('tenant')->table('user_department')->insert([
            'user_id' => $this->member->id, 'department_id' => $direction->id,
            'is_manager' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->project->update(['is_private' => true]);

        $this->actingAs($respDir);
        $this->get(route('projects.show', $this->project))->assertForbidden();
    }

    public function test_projet_prive_visible_pour_membre_explicite(): void
    {
        $this->project->update(['is_private' => true]);

        $this->actingAs($this->member);
        $this->get(route('projects.show', $this->project))->assertOk();
    }

    public function test_projet_prive_visible_pour_admin(): void
    {
        $this->project->update(['is_private' => true]);

        $this->actingAs($this->admin);
        $this->get(route('projects.show', $this->project))->assertOk();
    }

    public function test_toggle_is_private_dans_edition(): void
    {
        $this->actingAs($this->owner);

        $this->put(route('projects.update', $this->project), [
            'name' => $this->project->name,
            'status' => 'active',
            'is_private' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('projects', [
            'id' => $this->project->id,
            'is_private' => true,
        ], 'tenant');
    }
}
