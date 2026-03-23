<?php

namespace Tests\Feature\Admin;

use App\Models\Tenant\TenantSettings;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tests de la configuration de sécurité par tenant.
 * Couvre : session_lifetime, login_max_attempts, login_lockout_minutes.
 */
class SecuritySettingsTest extends TestCase
{
    private User $admin;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->user = User::factory()->create(['role' => 'user', 'status' => 'active']);
    }

    protected function tearDown(): void
    {
        DB::connection('tenant')->table('users')->delete();
        DB::connection('tenant')->table('tenant_settings')->delete();
        parent::tearDown();
    }

    public function test_admin_peut_accéder_à_la_page_sécurité(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.settings.security'))
            ->assertOk()
            ->assertSee('Sessions');
    }

    public function test_utilisateur_simple_ne_peut_pas_accéder(): void
    {
        $this->actingAs($this->user)
            ->get(route('admin.settings.security'))
            ->assertForbidden();
    }

    public function test_admin_peut_sauvegarder_les_paramètres(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.settings.security.update'), [
                'session_lifetime_minutes' => 240,
                'login_max_attempts' => 3,
                'login_lockout_minutes' => 30,
                'pwd_min_length' => 12,
                'pwd_history_count' => 5,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $settings = TenantSettings::first();
        $this->assertEquals(240, $settings->session_lifetime_minutes);
        $this->assertEquals(3, $settings->login_max_attempts);
        $this->assertEquals(30, $settings->login_lockout_minutes);
    }

    public function test_session_lifetime_minimum_5_minutes(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.settings.security.update'), [
                'session_lifetime_minutes' => 2,
                'login_max_attempts' => 5,
                'login_lockout_minutes' => 15,
            ])
            ->assertSessionHasErrors('session_lifetime_minutes');
    }

    public function test_session_lifetime_maximum_10080_minutes(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.settings.security.update'), [
                'session_lifetime_minutes' => 99999,
                'login_max_attempts' => 5,
                'login_lockout_minutes' => 15,
            ])
            ->assertSessionHasErrors('session_lifetime_minutes');
    }

    public function test_login_max_attempts_minimum_3(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.settings.security.update'), [
                'session_lifetime_minutes' => 120,
                'login_max_attempts' => 1,
                'login_lockout_minutes' => 15,
            ])
            ->assertSessionHasErrors('login_max_attempts');
    }

    public function test_utilisateur_simple_ne_peut_pas_sauvegarder(): void
    {
        $this->actingAs($this->user)
            ->put(route('admin.settings.security.update'), [
                'session_lifetime_minutes' => 60,
                'login_max_attempts' => 5,
                'login_lockout_minutes' => 15,
            ])
            ->assertForbidden();
    }

    public function test_session_lifetime_appliqué_depuis_tenant_settings(): void
    {
        TenantSettings::firstOrCreate([])->update(['session_lifetime_minutes' => 300]);

        $middleware = app(\App\Http\Middleware\ResolveTenant::class);
        $reflection = new \ReflectionMethod($middleware, 'applySessionLifetime');
        $reflection->setAccessible(true);
        $reflection->invoke($middleware);

        $this->assertEquals(300, Config::get('session.lifetime'));
    }

    public function test_session_lifetime_non_appliqué_si_zéro(): void
    {
        $original = Config::get('session.lifetime');
        TenantSettings::firstOrCreate([])->update(['session_lifetime_minutes' => 0]);

        $middleware = app(\App\Http\Middleware\ResolveTenant::class);
        $reflection = new \ReflectionMethod($middleware, 'applySessionLifetime');
        $reflection->setAccessible(true);
        $reflection->invoke($middleware);

        $this->assertEquals($original, Config::get('session.lifetime'));
    }
}
