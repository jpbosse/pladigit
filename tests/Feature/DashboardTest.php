<?php

namespace Tests\Feature;

use App\Models\Tenant\Department;
use App\Models\Tenant\Project;
use App\Models\Tenant\ProjectMember;
use App\Models\Tenant\Task;
use App\Models\Tenant\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests Feature — Dashboard.
 * Couvre le filtrage hiérarchique des statistiques selon le rôle.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $dgs;

    private User $respDirection;

    private User $respService;

    private User $simpleUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->dgs = User::factory()->create(['role' => 'dgs',   'status' => 'active']);

        $this->respDirection = User::factory()->create(['role' => 'resp_direction', 'status' => 'active']);
        $this->respService = User::factory()->create(['role' => 'resp_service',   'status' => 'active']);
        $this->simpleUser = User::factory()->create(['role' => 'user',            'status' => 'active']);
    }

    // ── Accès ──────────────────────────────────────────────────────────

    public function test_invité_ne_peut_pas_accéder_au_dashboard(): void
    {
        $this->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('allRolesProvider')]
    public function test_tous_les_rôles_peuvent_accéder_au_dashboard(string $roleKey): void
    {
        $this->actingAs($this->{$roleKey})
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewIs('dashboard');
    }

    public static function allRolesProvider(): array
    {
        return [
            'admin' => ['admin'],
            'dgs' => ['dgs'],
            'resp_direction' => ['respDirection'],
            'resp_service' => ['respService'],
            'user' => ['simpleUser'],
        ];
    }

    // ── Variables transmises à la vue ──────────────────────────────────

    public function test_dashboard_reçoit_les_stats_utilisateurs(): void
    {
        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertViewHas('totalUsers')
            ->assertViewHas('activeUsers')
            ->assertViewHas('ldapUsers')
            ->assertViewHas('adminUsers');
    }

    public function test_dashboard_reçoit_les_stats_départements(): void
    {
        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertViewHas('totalDirections')
            ->assertViewHas('totalServices');
    }

    public function test_dashboard_reçoit_activité_récente(): void
    {
        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertViewHas('recentLogins')
            ->assertViewHas('recentAudit');
    }

    public function test_dashboard_reçoit_org_et_user(): void
    {
        $this->actingAs($this->simpleUser)
            ->get(route('dashboard'))
            ->assertViewHas('org')
            ->assertViewHas('user');
    }

    public function test_dashboard_reçoit_visible_user_ids(): void
    {
        $this->actingAs($this->simpleUser)
            ->get(route('dashboard'))
            ->assertViewHas('visibleUserIds');
    }

    public function test_dashboard_reçoit_scoped_counts(): void
    {
        $this->actingAs($this->respService)
            ->get(route('dashboard'))
            ->assertViewHas('scopedUserCount')
            ->assertViewHas('scopedActiveCount');
    }

    // ── Filtrage hiérarchique ──────────────────────────────────────────

    public function test_admin_voit_tous_les_utilisateurs(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('dashboard'));

        $visibleIds = $response->viewData('visibleUserIds');
        $allIds = User::on('tenant')->pluck('id')->toArray();

        sort($visibleIds);
        sort($allIds);

        $this->assertEquals($allIds, $visibleIds);
    }

    public function test_dgs_voit_tous_les_utilisateurs(): void
    {
        $response = $this->actingAs($this->dgs)
            ->get(route('dashboard'));

        $visibleIds = $response->viewData('visibleUserIds');

        $this->assertCount(User::on('tenant')->count(), $visibleIds);
    }

    public function test_utilisateur_simple_ne_voit_que_lui_même(): void
    {
        $response = $this->actingAs($this->simpleUser)
            ->get(route('dashboard'));

        $visibleIds = $response->viewData('visibleUserIds');

        $this->assertCount(1, $visibleIds);
        $this->assertEquals([$this->simpleUser->id], $visibleIds);
    }

    public function test_resp_service_voit_uniquement_son_service(): void
    {
        // Créer un service et y affecter le responsable + un agent
        $service = Department::factory()->create(['type' => 'service']);
        $agent = User::factory()->create(['role' => 'user', 'status' => 'active']);

        $service->members()->attach($this->respService->id, ['is_manager' => true]);
        $service->members()->attach($agent->id, ['is_manager' => false]);

        $response = $this->actingAs($this->respService)->get(route('dashboard'));
        $visibleIds = $response->viewData('visibleUserIds');

        $this->assertContains($this->respService->id, $visibleIds);
        $this->assertContains($agent->id, $visibleIds);
        // Le simple user sans département ne doit PAS apparaître
        $this->assertNotContains($this->simpleUser->id, $visibleIds);
    }

    public function test_resp_direction_voit_sa_direction_et_ses_services(): void
    {
        $direction = Department::factory()->create(['type' => 'direction']);
        $service = Department::factory()->create(['type' => 'service', 'parent_id' => $direction->id]);

        $agentDir = User::factory()->create(['role' => 'user', 'status' => 'active']);
        $agentSvc = User::factory()->create(['role' => 'user', 'status' => 'active']);

        $direction->members()->attach($this->respDirection->id, ['is_manager' => true]);
        $direction->members()->attach($agentDir->id, ['is_manager' => false]);
        $service->members()->attach($agentSvc->id, ['is_manager' => false]);

        $response = $this->actingAs($this->respDirection)->get(route('dashboard'));
        $visibleIds = $response->viewData('visibleUserIds');

        $this->assertContains($this->respDirection->id, $visibleIds);
        $this->assertContains($agentDir->id, $visibleIds);
        $this->assertContains($agentSvc->id, $visibleIds);
        // Utilisateur hors périmètre
        $this->assertNotContains($this->simpleUser->id, $visibleIds);
    }

    // ── Valeurs correctes ──────────────────────────────────────────────

    public function test_total_users_est_correct(): void
    {
        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertViewHas('totalUsers', fn ($v) => is_int($v) && $v >= 5);
    }

    public function test_active_users_est_correct(): void
    {
        User::factory()->create(['status' => 'inactive']);

        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertViewHas('activeUsers', fn ($v) => is_int($v) && $v >= 5);
    }

    public function test_storage_per_org_vide_sans_super_admin(): void
    {
        // Sans session super_admin_logged_in, storagePerOrg doit être vide
        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertViewHas('storagePerOrg', []);
    }

    // ── Widgets Gestion de projet ──────────────────────────────────────

    public function test_dashboard_reçoit_variables_projets(): void
    {
        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertViewHas('myUrgentTasks')
            ->assertViewHas('myProjectsCount')
            ->assertViewHas('myActiveProjects');
    }

    public function test_my_projects_count_est_zero_sans_projet(): void
    {
        $response = $this->actingAs($this->simpleUser)
            ->get(route('dashboard'));

        $this->assertEquals(0, $response->viewData('myProjectsCount'));
    }

    public function test_my_urgent_tasks_contient_taches_assignees_urgentes(): void
    {
        $project = Project::factory()->create(['created_by' => $this->admin->id]);
        ProjectMember::create([
            'project_id' => $project->id,
            'user_id'    => $this->admin->id,
            'role'       => 'owner',
        ]);

        // Tâche urgente assignée à l'admin
        $urgentTask = Task::factory()->create([
            'project_id'  => $project->id,
            'created_by'  => $this->admin->id,
            'assigned_to' => $this->admin->id,
            'status'      => 'todo',
            'priority'    => 'urgent',
        ]);

        // Tâche basse priorité — ne doit PAS apparaître
        Task::factory()->create([
            'project_id'  => $project->id,
            'created_by'  => $this->admin->id,
            'assigned_to' => $this->admin->id,
            'status'      => 'todo',
            'priority'    => 'low',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('dashboard'));

        $tasks = $response->viewData('myUrgentTasks');
        $ids = $tasks->pluck('id')->toArray();

        $this->assertContains($urgentTask->id, $ids);
        // Tâche low priority absente
        $this->assertCount(1, $tasks);
    }
}
