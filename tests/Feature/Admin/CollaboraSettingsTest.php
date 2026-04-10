<?php

namespace Tests\Feature\Admin;

use App\Models\Tenant\TenantSettings;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Tests Feature — Paramètres Collabora Online (Jalon 16).
 */
class CollaboraSettingsTest extends TestCase
{
    private User $admin;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->agent = User::factory()->create(['role' => 'user', 'status' => 'active']);
    }

    // ── Accès ──────────────────────────────────────────────────────────────────

    public function test_invité_redirigé_vers_login(): void
    {
        $this->get(route('admin.settings.collabora'))
            ->assertRedirect(route('login'));
    }

    public function test_non_admin_ne_peut_pas_accéder(): void
    {
        $this->actingAs($this->agent)
            ->get(route('admin.settings.collabora'))
            ->assertForbidden();
    }

    public function test_admin_peut_voir_la_page_collabora(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.settings.collabora'))
            ->assertOk()
            ->assertViewIs('admin.settings.collabora');
    }

    // ── Sauvegarde ─────────────────────────────────────────────────────────────

    public function test_admin_peut_sauvegarder_url_collabora(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.settings.collabora.update'), [
                'collabora_url' => 'https://collabora.mairie.fr',
                'wopi_url' => '',
                'collabora_token_ttl_minutes' => 240,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('tenant_settings', [
            'collabora_url' => 'https://collabora.mairie.fr',
        ], 'tenant');
    }

    public function test_champs_vides_acceptés(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.settings.collabora.update'), [
                'collabora_url' => '',
                'wopi_url' => '',
                'collabora_token_ttl_minutes' => null,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    public function test_url_invalide_refusée(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.settings.collabora.update'), [
                'collabora_url' => 'pas-une-url',
            ])
            ->assertSessionHasErrors('collabora_url');
    }

    public function test_ttl_trop_court_refusé(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.settings.collabora.update'), [
                'collabora_token_ttl_minutes' => 2,
            ])
            ->assertSessionHasErrors('collabora_token_ttl_minutes');
    }

    public function test_ttl_trop_long_refusé(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.settings.collabora.update'), [
                'collabora_token_ttl_minutes' => 99999,
            ])
            ->assertSessionHasErrors('collabora_token_ttl_minutes');
    }

    // ── Test connexion ──────────────────────────────────────────────────────────

    public function test_test_connexion_url_vide_retourne_erreur(): void
    {
        // Vider aussi la config .env pour simuler une instance sans Collabora
        config(['collabora.url' => '']);
        TenantSettings::firstOrCreate([])->update(['collabora_url' => null]);

        $this->actingAs($this->admin)
            ->get(route('admin.settings.collabora.test'))
            ->assertJson(['ok' => false]);
    }

    public function test_test_connexion_réussie(): void
    {
        Http::fake([
            '*/hosting/capabilities' => Http::response(['product' => 'Collabora Online'], 200),
        ]);

        TenantSettings::firstOrCreate([])->update(['collabora_url' => 'https://collabora.example.com']);

        $this->actingAs($this->admin)
            ->get(route('admin.settings.collabora.test'))
            ->assertJson(['ok' => true]);
    }

    public function test_test_connexion_erreur_http_retourne_ko(): void
    {
        Http::fake([
            '*/hosting/capabilities' => Http::response('', 503),
        ]);

        TenantSettings::firstOrCreate([])->update(['collabora_url' => 'https://collabora.example.com']);

        $this->actingAs($this->admin)
            ->get(route('admin.settings.collabora.test'))
            ->assertJson(['ok' => false]);
    }
}
