<?php

namespace Tests\Feature\Projects;

use App\Models\Tenant\Project;
use App\Models\Tenant\ProjectMember;
use App\Models\Tenant\User;
use Tests\TestCase;

/**
 * Tests de la politique d'accès (ProjectPolicy).
 * Vérifie les 3 couches : Admin/Président/DGS, owner, member, viewer, non-membre.
 */
class ProjectPolicyTest extends TestCase
{
    private User $admin;

    private User $dgs;

    private User $owner;

    private User $viewer;

    private User $outsider;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin',    'status' => 'active']);
        $this->dgs = User::factory()->create(['role' => 'dgs',      'status' => 'active']);
        $this->owner = User::factory()->create(['role' => 'resp_direction', 'status' => 'active']);
        $this->viewer = User::factory()->create(['role' => 'user',     'status' => 'active']);
        $this->outsider = User::factory()->create(['role' => 'user',     'status' => 'active']);

        $this->project = Project::factory()->create(['created_by' => $this->owner->id]);
        ProjectMember::create(['project_id' => $this->project->id, 'user_id' => $this->owner->id,  'role' => 'owner']);
        ProjectMember::create(['project_id' => $this->project->id, 'user_id' => $this->viewer->id, 'role' => 'viewer']);
    }

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

    public function test_admin_peut_modifier_tout_projet(): void
    {
        $this->actingAs($this->admin);
        $this->put(route('projects.update', $this->project), ['name' => 'Modifié admin', 'status' => 'active'])->assertRedirect();
    }

    public function test_viewer_ne_peut_pas_modifier_le_projet(): void
    {
        $this->actingAs($this->viewer);
        $this->put(route('projects.update', $this->project), ['name' => 'Tentative', 'status' => 'active'])->assertForbidden();
    }

    public function test_non_membre_obtient_403_sur_modification(): void
    {
        $this->actingAs($this->outsider);
        $this->put(route('projects.update', $this->project), ['name' => 'Hack', 'status' => 'active'])->assertForbidden();
    }
}

// ─────────────────────────────────────────────────────────────────────────────
