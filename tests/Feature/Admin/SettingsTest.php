<?php

namespace Tests\Feature\Admin;

use App\Models\Tenant\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Tests Feature — Paramètres de l'organisation (branding).
 */
class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role'   => 'admin',
            'status' => 'active',
        ]);
    }

    // ── Accès ──────────────────────────────────────────────────────────

    public function test_invité_ne_peut_pas_accéder_au_branding(): void
    {
        $this->get(route('admin.settings.branding'))
            ->assertRedirect(route('login'));
    }

    public function test_admin_peut_voir_le_branding(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.settings.branding'))
            ->assertOk()
            ->assertViewIs('admin.settings.branding');
    }

    // ── Branding ───────────────────────────────────────────────────────

    public function test_admin_peut_changer_la_couleur_principale(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.settings.branding.update'), [
                'primary_color' => '#2D7DD2',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    public function test_couleur_invalide_refusée(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.settings.branding.update'), [
                'primary_color' => 'rouge',
            ])
            ->assertSessionHasErrors('primary_color');
    }

    public function test_couleur_sans_dièse_refusée(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.settings.branding.update'), [
                'primary_color' => '2D7DD2',
            ])
            ->assertSessionHasErrors('primary_color');
    }

    public function test_admin_peut_uploader_un_logo(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)
            ->post(route('admin.settings.branding.update'), [
                'primary_color' => '#1E3A5F',
                'logo'          => UploadedFile::fake()->image('logo.png', 200, 200),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    public function test_logo_trop_lourd_refusé(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)
            ->post(route('admin.settings.branding.update'), [
                'logo' => UploadedFile::fake()->image('logo.png')->size(3000),
            ])
            ->assertSessionHasErrors('logo');
    }

    public function test_logo_mauvais_format_refusé(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)
            ->post(route('admin.settings.branding.update'), [
                'logo' => UploadedFile::fake()->create('logo.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasErrors('logo');
    }

    public function test_image_fond_login_uploadée(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)
            ->post(route('admin.settings.branding.update'), [
                'login_bg' => UploadedFile::fake()->image('bg.jpg', 1920, 1080),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    public function test_fond_login_mauvais_format_refusé(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)
            ->post(route('admin.settings.branding.update'), [
                'login_bg' => UploadedFile::fake()->create('bg.svg', 100, 'image/svg+xml'),
            ])
            ->assertSessionHasErrors('login_bg');
    }
}
