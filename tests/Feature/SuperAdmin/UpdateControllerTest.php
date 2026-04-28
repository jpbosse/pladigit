<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Platform\PlatformSettings;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UpdateControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        PlatformSettings::query()->delete();
    }

    private function actingAsSuperAdmin()
    {
        return $this->withSession([
            'super_admin_email' => config('superadmin.email'),
            'super_admin_verified' => true,
        ]);
    }

    // ── Accès ──────────────────────────────────────────────────────────

    public function test_super_admin_peut_voir_la_page_update(): void
    {
        $this->actingAsSuperAdmin()
            ->get(route('super-admin.update'))
            ->assertOk()
            ->assertViewIs('super-admin.update');
    }

    public function test_non_super_admin_ne_peut_pas_acceder_update(): void
    {
        $this->get(route('super-admin.update'))
            ->assertRedirect(route('super-admin.login'));
    }

    public function test_non_super_admin_ne_peut_pas_lancer_update(): void
    {
        $this->post(route('super-admin.update.run'))
            ->assertRedirect(route('super-admin.login'));
    }

    // ── run() ──────────────────────────────────────────────────────────

    public function test_run_retourne_erreur_si_mise_a_jour_en_cours(): void
    {
        PlatformSettings::create(['update_last_status' => 'running']);

        $response = $this->actingAsSuperAdmin()
            ->postJson(route('super-admin.update.run'));

        $response->assertOk()
            ->assertJson(['ok' => false])
            ->assertJsonFragment(['message' => 'Une mise à jour est déjà en cours.']);
    }

    // ── status() ───────────────────────────────────────────────────────

    public function test_status_retourne_le_bon_format_json(): void
    {
        PlatformSettings::create([
            'update_last_status' => 'success',
            'update_last_message' => 'Terminé',
            'update_last_run_at' => now(),
            'update_current_version' => '1.2.3',
            'update_available_version' => '1.2.4',
        ]);

        $response = $this->actingAsSuperAdmin()
            ->getJson(route('super-admin.update.status'));

        $response->assertOk()
            ->assertJsonStructure(['status', 'message', 'last_run', 'current_version', 'available_version'])
            ->assertJsonFragment([
                'status' => 'success',
                'message' => 'Terminé',
                'current_version' => '1.2.3',
                'available_version' => '1.2.4',
            ]);
    }

    public function test_status_retourne_valeurs_nulles_si_aucune_mise_a_jour(): void
    {
        $response = $this->actingAsSuperAdmin()
            ->getJson(route('super-admin.update.status'));

        $response->assertOk()
            ->assertJsonFragment([
                'status' => null,
                'message' => null,
                'last_run' => null,
            ]);
    }

    // ── checkVersion() ─────────────────────────────────────────────────

    public function test_check_version_retourne_la_version_github(): void
    {
        Http::fake([
            'api.github.com/*' => Http::response([
                ['name' => 'v1.5.0'],
                ['name' => 'v1.4.2'],
            ], 200),
        ]);

        $response = $this->actingAsSuperAdmin()
            ->getJson(route('super-admin.update.check-version'));

        $response->assertOk()
            ->assertJson(['ok' => true, 'version' => '1.5.0']);

        $this->assertEquals('1.5.0', PlatformSettings::first()->update_available_version);
    }

    public function test_check_version_gracieux_si_github_indisponible(): void
    {
        Http::fake([
            'api.github.com/*' => Http::response([], 503),
        ]);

        $response = $this->actingAsSuperAdmin()
            ->getJson(route('super-admin.update.check-version'));

        $response->assertOk()
            ->assertJson(['ok' => false]);
    }

    public function test_check_version_gracieux_si_erreur_reseau(): void
    {
        Http::fake([
            'api.github.com/*' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Network error');
            },
        ]);

        $response = $this->actingAsSuperAdmin()
            ->getJson(route('super-admin.update.check-version'));

        $response->assertOk()
            ->assertJson(['ok' => false]);
    }
}
