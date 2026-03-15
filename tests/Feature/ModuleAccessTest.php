<?php

namespace Tests\Feature;

use App\Enums\ModuleKey;
use App\Models\Platform\Organization;
use App\Models\Tenant\User;
use App\Services\TenantManager;
use Tests\TestCase;

/**
 * Tests Feature — Modules activables par organisation.
 *
 * Couvre :
 *   - Organization::hasModule() / enableModule() / disableModule()
 *   - Middleware RequireModule : accès accordé / refusé
 *   - Super Admin : updateModules sauvegarde correctement
 *   - Clés invalides ignorées (sécurité)
 *   - Organisation sans modules : accès refusé
 */
class ModuleAccessTest extends TestCase
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
     * Persiste l'org courante en base et reconnecte TenantManager.
     * Nécessaire car TestCase::setUp() crée une Organization en mémoire sans save().
     */
    private function persistCurrentOrg(array $extra = []): Organization
    {
        $current = app(TenantManager::class)->current();

        $org = Organization::forceCreate(array_merge([
            'slug' => $current->slug ?? 'test',
            'name' => $current->name ?? 'Test Org',
            'db_name' => $current->db_name ?? env('DB_TENANT_DATABASE'),
            'status' => 'active',
            'plan' => 'communautaire',
            'primary_color' => '#1E3A5F',
            'enabled_modules' => [],
        ], $extra));

        app(TenantManager::class)->connectTo($org);

        return $org;
    }

    // ── Organization::hasModule() ──────────────────────────────────────

    public function test_has_module_retourne_true_si_module_activé(): void
    {
        $org = Organization::make(['enabled_modules' => ['media']]);

        $this->assertTrue($org->hasModule(ModuleKey::MEDIA));
    }

    public function test_has_module_retourne_false_si_module_absent(): void
    {
        $org = Organization::make(['enabled_modules' => []]);

        $this->assertFalse($org->hasModule(ModuleKey::MEDIA));
    }

    public function test_has_module_retourne_false_si_enabled_modules_null(): void
    {
        $org = Organization::make(['enabled_modules' => null]);

        $this->assertFalse($org->hasModule(ModuleKey::GED));
    }

    public function test_enable_module_ajoute_le_module(): void
    {
        $org = Organization::make(['enabled_modules' => []]);
        $org->enableModule(ModuleKey::MEDIA);

        $this->assertTrue($org->hasModule(ModuleKey::MEDIA));
    }

    public function test_enable_module_n_ajoute_pas_en_double(): void
    {
        $org = Organization::make(['enabled_modules' => ['media']]);
        $org->enableModule(ModuleKey::MEDIA);

        $this->assertCount(1, $org->enabled_modules);
    }

    public function test_disable_module_supprime_le_module(): void
    {
        $org = Organization::make(['enabled_modules' => ['media', 'ged']]);
        $org->disableModule(ModuleKey::MEDIA);

        $this->assertFalse($org->hasModule(ModuleKey::MEDIA));
        $this->assertTrue($org->hasModule(ModuleKey::GED));
    }

    public function test_active_modules_retourne_les_instances_enum(): void
    {
        $org = Organization::make(['enabled_modules' => ['media']]);
        $active = $org->activeModules();

        $this->assertCount(1, $active);
        $this->assertSame(ModuleKey::MEDIA, $active[0]);
    }

    // ── Middleware RequireModule ───────────────────────────────────────

    public function test_accès_photothèque_accordé_si_module_media_activé(): void
    {
        $this->persistCurrentOrg(['enabled_modules' => ['media']]);

        $this->actingAs($this->admin, 'tenant')
            ->get(route('media.albums.index'))
            ->assertOk();
    }

    public function test_accès_photothèque_refusé_si_module_media_désactivé(): void
    {
        $this->persistCurrentOrg(['enabled_modules' => []]);

        $this->actingAs($this->admin, 'tenant')
            ->get(route('media.albums.index'))
            ->assertForbidden();
    }

    public function test_accès_photothèque_refusé_si_enabled_modules_null(): void
    {
        $this->persistCurrentOrg(['enabled_modules' => null]);

        $this->actingAs($this->admin, 'tenant')
            ->get(route('media.albums.index'))
            ->assertForbidden();
    }

    // ── Super Admin : updateModules ────────────────────────────────────

    public function test_super_admin_peut_activer_le_module_media(): void
    {
        $org = Organization::factory()->create(['enabled_modules' => []]);

        $this->withSession(['super_admin_email' => config('superadmin.email'), 'super_admin_verified' => true])
            ->post(route('super-admin.organizations.update-modules', $org), [
                'modules' => ['media'],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $org->refresh();
        $this->assertTrue($org->hasModule(ModuleKey::MEDIA));
    }

    public function test_super_admin_peut_désactiver_tous_les_modules(): void
    {
        $org = Organization::factory()->create(['enabled_modules' => ['media']]);

        $this->withSession(['super_admin_email' => config('superadmin.email'), 'super_admin_verified' => true])
            ->post(route('super-admin.organizations.update-modules', $org), [
                // pas de modules[] soumis
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $org->refresh();
        $this->assertFalse($org->hasModule(ModuleKey::MEDIA));
    }

    public function test_clé_module_invalide_est_ignorée(): void
    {
        $org = Organization::factory()->create(['enabled_modules' => []]);

        $this->withSession(['super_admin_email' => config('superadmin.email'), 'super_admin_verified' => true])
            ->post(route('super-admin.organizations.update-modules', $org), [
                'modules' => ['media', 'module_inexistant', '<script>'],
            ])
            ->assertRedirect();

        $org->refresh();
        // Seul 'media' est valide — les autres sont ignorés
        $this->assertSame(['media'], $org->enabled_modules);
    }

    public function test_invité_ne_peut_pas_modifier_les_modules(): void
    {
        $org = Organization::factory()->create();

        $this->post(route('super-admin.organizations.update-modules', $org), [
            'modules' => ['media'],
        ])->assertRedirect(route('super-admin.login'));
    }
}
