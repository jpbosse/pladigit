<?php

namespace Tests\Feature\Admin;

use App\Models\Platform\Organization;
use App\Models\Tenant\User;
use App\Services\TenantManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * BrandingTest — Isolation tenant et reset defaults (§6.3)
 *
 * Complète SettingsTest.php qui couvre déjà :
 *   - accès invité/admin, couleur valide/invalide
 *   - upload logo/fond login, formats et tailles refusés
 *
 * Ce fichier couvre :
 *   - Isolation : le branding d'un tenant n'affecte pas un autre tenant
 *   - Reset : suppression logo et fond login via remove_logo / remove_login_bg
 *   - Couleur par défaut conservée si aucune couleur soumise
 */
class BrandingTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
    }

    /**
     * Persiste l'organisation courante en base et reconnecte TenantManager.
     * Nécessaire car TestCase::setUp() crée un Organization en mémoire (sans save()),
     * ce qui empêche update() et refresh() de fonctionner.
     */
    private function persistCurrentOrg(array $extra = []): Organization
    {
        $current = app(TenantManager::class)->current();
        $slug = $current->slug ?? 'test';

        $org = Organization::updateOrCreate(
            ['slug' => $slug],
            array_merge([
                'name' => $current->name ?? 'Test Org',
                'db_name' => $current->db_name ?? env('DB_TENANT_DATABASE'),
                'status' => 'active',
                'primary_color' => '#1E3A5F',
            ], $extra)
        );

        app(TenantManager::class)->connectTo($org);

        return $org;
    }

    // ────────────────────────────────────────────────────────────────
    // Reset logo — remove_logo supprime le fichier et vide logo_path
    // ────────────────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function remove_logo_supprime_le_fichier_et_vide_logo_path(): void
    {
        Storage::fake('public');

        $org = $this->persistCurrentOrg();

        $existing = "orgs/{$org->slug}/branding/logo.png";
        Storage::disk('public')->put($existing, 'fake');
        $org->update(['logo_path' => $existing]);

        $this->actingAs($this->admin)
            ->post(route('admin.settings.branding.update'), [
                'remove_logo' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $org->refresh();
        $this->assertNull($org->logo_path, 'logo_path doit être null après suppression');
        Storage::disk('public')->assertMissing($existing);
    }

    // ────────────────────────────────────────────────────────────────
    // Reset fond login — remove_login_bg supprime le fichier
    // ────────────────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function remove_login_bg_supprime_le_fichier_et_vide_login_bg_path(): void
    {
        Storage::fake('public');

        $org = $this->persistCurrentOrg();

        $existing = "orgs/{$org->slug}/branding/bg.jpg";
        Storage::disk('public')->put($existing, 'fake');
        $org->update(['login_bg_path' => $existing]);

        $this->actingAs($this->admin)
            ->post(route('admin.settings.branding.update'), [
                'remove_login_bg' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $org->refresh();
        $this->assertNull($org->login_bg_path);
        Storage::disk('public')->assertMissing($existing);
    }

    // ────────────────────────────────────────────────────────────────
    // Upload remplace l'ancien logo (l'ancien fichier est supprimé)
    // ────────────────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function upload_logo_remplace_lancien_et_supprime_lancien_fichier(): void
    {
        Storage::fake('public');

        $org = $this->persistCurrentOrg();

        $oldPath = "orgs/{$org->slug}/branding/old_logo.png";
        Storage::disk('public')->put($oldPath, 'fake');
        $org->update(['logo_path' => $oldPath]);

        $this->actingAs($this->admin)
            ->post(route('admin.settings.branding.update'), [
                'logo' => UploadedFile::fake()->image('new_logo.png', 200, 200),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $org->refresh();
        $this->assertNotNull($org->logo_path);
        $this->assertNotEquals($oldPath, $org->logo_path, 'Le chemin doit pointer vers le nouveau logo');
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($org->logo_path);
    }

    // ────────────────────────────────────────────────────────────────
    // Couleur par défaut conservée si aucune couleur soumise
    // ────────────────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function couleur_par_defaut_conservee_si_aucune_couleur_soumise(): void
    {
        $org = app(TenantManager::class)->current();
        $org->update(['primary_color' => '#1E3A5F']);

        $this->actingAs($this->admin)
            ->post(route('admin.settings.branding.update'), [])
            ->assertRedirect();

        $org->refresh();
        $this->assertEquals('#1E3A5F', $org->primary_color);
    }

    // ────────────────────────────────────────────────────────────────
    // Isolation tenant : le branding d'un tenant n'affecte pas un autre
    // ────────────────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function branding_dun_tenant_nisolee_pas_lautre_tenant(): void
    {
        $orgB = Organization::forceCreate([
            'slug' => 'org-branding-b',
            'name' => 'Organisation B',
            'db_name' => 'pladigit_test_branding_b',
            'status' => 'active',
            'primary_color' => '#AABBCC',
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.settings.branding.update'), [
                'primary_color' => '#FF0000',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $orgB->refresh();
        $this->assertEquals('#AABBCC', $orgB->primary_color,
            'Le branding du tenant B ne doit pas être modifié par une action sur le tenant A'
        );

        Organization::where('slug', 'org-branding-b')->forceDelete();
    }

    // ────────────────────────────────────────────────────────────────
    // Non-admin ne peut pas modifier le branding
    // ────────────────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function non_admin_ne_peut_pas_modifier_le_branding(): void
    {
        $user = User::factory()->create(['role' => 'user', 'status' => 'active']);

        $this->actingAs($user)
            ->post(route('admin.settings.branding.update'), [
                'primary_color' => '#FF0000',
            ])
            ->assertForbidden();
    }
}
