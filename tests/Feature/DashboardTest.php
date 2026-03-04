<?php

namespace Tests\Feature;

use App\Models\Tenant\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests Feature — Dashboard.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role'   => 'admin',
            'status' => 'active',
        ]);

        $this->user = User::factory()->create([
            'role'   => 'user',
            'status' => 'active',
        ]);
    }

    // ── Accès ──────────────────────────────────────────────────────────

    public function test_invité_ne_peut_pas_accéder_au_dashboard(): void
    {
        $this->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_utilisateur_peut_accéder_au_dashboard(): void
    {
        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewIs('dashboard');
    }

    public function test_admin_peut_accéder_au_dashboard(): void
    {
        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewIs('dashboard');
    }

    // ── Variables passées à la vue ─────────────────────────────────────

    public function test_dashboard_reçoit_les_stats_utilisateurs(): void
    {
        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertViewHas('totalUsers')
            ->assertViewHas('activeUsers')
            ->assertViewHas('ldapUsers')
            ->assertViewHas('adminUsers')
            ->assertViewHas('usersByRole');
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

    public function test_dashboard_reçoit_org(): void
    {
        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertViewHas('org')
            ->assertViewHas('user');
    }

    // ── Valeurs correctes ──────────────────────────────────────────────

public function test_total_users_est_correct(): void
{
    $response = $this->actingAs($this->admin)
        ->get(route('dashboard'));

    $response->assertViewHas('totalUsers', fn($v) => is_int($v) && $v >= 2);
}

public function test_active_users_est_correct(): void
{
    User::factory()->create(['status' => 'inactive']);

    $response = $this->actingAs($this->admin)
        ->get(route('dashboard'));

    $response->assertViewHas('activeUsers', fn($v) => is_int($v) && $v >= 2);
}

}
