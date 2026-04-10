<?php

namespace Tests\Feature\Ged;

use App\Enums\GedPermissionLevel;
use App\Enums\UserRole;
use App\Models\Tenant\GedDocument;
use App\Models\Tenant\GedFolder;
use App\Models\Tenant\GedFolderPermission;
use App\Models\Tenant\User;
use App\Services\Ged\GedPermissionService;
use Tests\TestCase;

/**
 * Tests fonctionnels — GED Jalon 5 : Droits et héritage.
 *
 * Couverture :
 *   - GedPermissionService : résolution, héritage, bypass DGS, créateur
 *   - Permissions rôle / user individuel
 *   - Exclusion explicite (none coupe l'héritage)
 *   - GedPolicy : view, upload, update, delete, managePermissions, viewDocument, manageDocument
 *   - Interface droits par dossier (GedPermissionController)
 *   - Interface gouvernance admin (AdminGedController)
 */
class GedPermissionTest extends TestCase
{
    private GedPermissionService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(GedPermissionService::class);
    }

    // ── Helpers ──────────────────────────────────────────────

    private function makeUser(UserRole $role): User
    {
        return User::factory()->create(['role' => $role->value]);
    }

    private function makeFolder(array $attrs = []): GedFolder
    {
        return GedFolder::create(array_merge([
            'name' => 'Dossier',
            'slug' => 'dossier-'.uniqid(),
            'path' => '/dossier-'.uniqid(),
            'parent_id' => null,
            'is_private' => false,
            'created_by' => $this->makeUser(UserRole::ADMIN)->id,
        ], $attrs));
    }

    private function makeDoc(GedFolder $folder, User $creator): GedDocument
    {
        return GedDocument::create([
            'folder_id' => $folder->id,
            'name' => 'doc.pdf',
            'disk_path' => 'test/doc.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'created_by' => $creator->id,
            'current_version' => 1,
        ]);
    }

    // =========================================================================
    // GedPermissionService — bypass et créateur
    // =========================================================================

    public function test_dgs_a_toujours_admin(): void
    {
        $dgs = $this->makeUser(UserRole::DGS);
        $folder = $this->makeFolder();

        $this->assertSame(GedPermissionLevel::Admin, $this->svc->effectiveLevel($dgs, $folder));
    }

    public function test_createur_a_toujours_admin(): void
    {
        $user = $this->makeUser(UserRole::USER);
        $folder = $this->makeFolder(['created_by' => $user->id]);

        $this->assertSame(GedPermissionLevel::Admin, $this->svc->effectiveLevel($user, $folder));
    }

    public function test_sans_permission_retourne_none(): void
    {
        $user = $this->makeUser(UserRole::USER);
        $folder = $this->makeFolder();

        $this->assertSame(GedPermissionLevel::None, $this->svc->effectiveLevel($user, $folder));
    }

    // =========================================================================
    // GedPermissionService — permission par rôle
    // =========================================================================

    public function test_permission_role_accordee(): void
    {
        $user = $this->makeUser(UserRole::RESP_SERVICE);
        $folder = $this->makeFolder();

        $this->svc->setRolePermission($folder, UserRole::RESP_SERVICE->value, GedPermissionLevel::Download);

        $this->assertSame(GedPermissionLevel::Download, $this->svc->effectiveLevel($user, $folder));
    }

    public function test_permission_role_hierarchique_s_applique_aux_roles_superieurs(): void
    {
        // Pivot = RESP_SERVICE → s'applique à RESP_SERVICE et plus haut (DGS, etc.)
        // Mais DGS bypass déjà via la règle admin — tester avec RESP_DIRECTION
        $respDir = $this->makeUser(UserRole::RESP_DIRECTION);
        $folder = $this->makeFolder();

        $this->svc->setRolePermission($folder, UserRole::RESP_SERVICE->value, GedPermissionLevel::Upload);

        // RESP_DIRECTION (level 4) <= RESP_SERVICE (level 5) → s'applique
        $this->assertSame(GedPermissionLevel::Upload, $this->svc->effectiveLevel($respDir, $folder));
    }

    public function test_permission_role_ne_s_applique_pas_aux_roles_inferieurs(): void
    {
        $agent = $this->makeUser(UserRole::USER); // level 6
        $folder = $this->makeFolder();

        // Pivot = RESP_SERVICE (level 5) — USER (level 6) > 5 → ne s'applique pas
        $this->svc->setRolePermission($folder, UserRole::RESP_SERVICE->value, GedPermissionLevel::View);

        $this->assertSame(GedPermissionLevel::None, $this->svc->effectiveLevel($agent, $folder));
    }

    // =========================================================================
    // GedPermissionService — permission individuelle
    // =========================================================================

    public function test_permission_individuelle_appliquee(): void
    {
        $user = $this->makeUser(UserRole::USER);
        $folder = $this->makeFolder();

        $this->svc->setUserPermission($folder, $user, GedPermissionLevel::Upload);

        $this->assertSame(GedPermissionLevel::Upload, $this->svc->effectiveLevel($user, $folder));
    }

    public function test_permission_individuelle_prime_sur_role(): void
    {
        $user = $this->makeUser(UserRole::RESP_SERVICE);
        $folder = $this->makeFolder();

        // Rôle = View, individuel = None (exclusion explicite)
        $this->svc->setRolePermission($folder, UserRole::RESP_SERVICE->value, GedPermissionLevel::View);
        $this->svc->setUserPermission($folder, $user, GedPermissionLevel::None);

        $this->assertSame(GedPermissionLevel::None, $this->svc->effectiveLevel($user, $folder));
    }

    // =========================================================================
    // GedPermissionService — héritage parent → enfant
    // =========================================================================

    public function test_heritage_depuis_parent(): void
    {
        $user = $this->makeUser(UserRole::USER);
        $parent = $this->makeFolder();
        $child = $this->makeFolder([
            'parent_id' => $parent->id,
            'path' => $parent->path.'/child',
            'created_by' => $this->makeUser(UserRole::ADMIN)->id,
        ]);

        // Permission définie sur le parent uniquement
        $this->svc->setUserPermission($parent, $user, GedPermissionLevel::Download);

        // L'enfant hérite
        $this->assertSame(GedPermissionLevel::Download, $this->svc->effectiveLevel($user, $child));
    }

    public function test_override_enfant_coupe_heritage(): void
    {
        $user = $this->makeUser(UserRole::USER);
        $parent = $this->makeFolder();
        $child = $this->makeFolder([
            'parent_id' => $parent->id,
            'path' => $parent->path.'/child',
            'created_by' => $this->makeUser(UserRole::ADMIN)->id,
        ]);

        $this->svc->setUserPermission($parent, $user, GedPermissionLevel::Download);
        // Override = None sur l'enfant → coupe l'héritage
        $this->svc->setUserPermission($child, $user, GedPermissionLevel::None);

        $this->assertSame(GedPermissionLevel::None, $this->svc->effectiveLevel($user, $child));
    }

    // =========================================================================
    // GedPermissionService — revokeAll
    // =========================================================================

    public function test_revoke_all_supprime_toutes_les_permissions(): void
    {
        $user = $this->makeUser(UserRole::USER);
        $folder = $this->makeFolder();

        $this->svc->setUserPermission($folder, $user, GedPermissionLevel::Upload);
        $this->svc->setRolePermission($folder, UserRole::RESP_SERVICE->value, GedPermissionLevel::View);

        $this->svc->revokeAll($folder);

        $this->assertDatabaseMissing('ged_folder_permissions', ['folder_id' => $folder->id], 'tenant');
        $this->assertDatabaseMissing('ged_folder_user_permissions', ['folder_id' => $folder->id], 'tenant');
    }

    // =========================================================================
    // GedPolicy — via routes
    // =========================================================================

    public function test_agent_sans_permission_ne_peut_pas_voir_un_dossier(): void
    {
        $agent = $this->makeUser(UserRole::USER);
        $folder = $this->makeFolder();

        $this->actingAs($agent)
            ->get(route('ged.folders.show', $folder))
            ->assertForbidden();
    }

    public function test_agent_avec_permission_view_peut_voir_un_dossier(): void
    {
        $agent = $this->makeUser(UserRole::USER);
        $folder = $this->makeFolder();

        $this->svc->setUserPermission($folder, $agent, GedPermissionLevel::View);

        $this->actingAs($agent)
            ->get(route('ged.folders.show', $folder))
            ->assertOk();
    }

    public function test_agent_avec_permission_view_ne_peut_pas_uploader(): void
    {
        $agent = $this->makeUser(UserRole::USER);
        $folder = $this->makeFolder();

        $this->svc->setUserPermission($folder, $agent, GedPermissionLevel::View);

        $this->actingAs($agent)
            ->postJson(route('ged.documents.store'), [
                'folder_id' => $folder->id,
                'files' => [],
            ])
            ->assertForbidden();
    }

    public function test_agent_avec_permission_admin_peut_supprimer_dossier(): void
    {
        $agent = $this->makeUser(UserRole::USER);
        $folder = $this->makeFolder();

        $this->svc->setUserPermission($folder, $agent, GedPermissionLevel::Admin);

        $this->actingAs($agent)
            ->deleteJson(route('ged.folders.destroy', $folder))
            ->assertOk();
    }

    // =========================================================================
    // Interface droits par dossier (GedPermissionController)
    // =========================================================================

    public function test_seul_admin_dossier_peut_acceder_interface_droits(): void
    {
        $agent = $this->makeUser(UserRole::USER);
        $folder = $this->makeFolder();

        $this->svc->setUserPermission($folder, $agent, GedPermissionLevel::View);

        $this->actingAs($agent)
            ->get(route('ged.permissions.index', $folder))
            ->assertForbidden();
    }

    public function test_admin_dossier_voit_interface_droits(): void
    {
        $user = $this->makeUser(UserRole::USER);
        $folder = $this->makeFolder(['created_by' => $user->id]);

        $this->actingAs($user)
            ->get(route('ged.permissions.index', $folder))
            ->assertOk();
    }

    public function test_set_role_permission_enregistre_en_base(): void
    {
        $dgs = $this->makeUser(UserRole::DGS);
        $folder = $this->makeFolder();

        $this->actingAs($dgs)
            ->post(route('ged.permissions.set-role', $folder), [
                'role' => UserRole::RESP_SERVICE->value,
                'level' => GedPermissionLevel::Download->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('ged_folder_permissions', [
            'folder_id' => $folder->id,
            'subject_type' => 'role',
            'subject_role' => UserRole::RESP_SERVICE->value,
            'level' => GedPermissionLevel::Download->value,
        ], 'tenant');
    }

    public function test_set_user_permission_enregistre_en_base(): void
    {
        $dgs = $this->makeUser(UserRole::DGS);
        $target = $this->makeUser(UserRole::USER);
        $folder = $this->makeFolder();

        $this->actingAs($dgs)
            ->post(route('ged.permissions.set-user', $folder), [
                'user_id' => $target->id,
                'level' => GedPermissionLevel::Upload->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('ged_folder_user_permissions', [
            'folder_id' => $folder->id,
            'user_id' => $target->id,
            'level' => GedPermissionLevel::Upload->value,
        ], 'tenant');
    }

    public function test_destroy_subject_supprime_permission(): void
    {
        $dgs = $this->makeUser(UserRole::DGS);
        $folder = $this->makeFolder();

        $perm = GedFolderPermission::create([
            'folder_id' => $folder->id,
            'subject_type' => 'role',
            'subject_id' => null,
            'subject_role' => UserRole::RESP_SERVICE->value,
            'level' => GedPermissionLevel::View->value,
        ]);

        $this->actingAs($dgs)
            ->deleteJson(route('ged.permissions.destroy-subject', $folder), [
                'permission_id' => $perm->id,
            ])
            ->assertJson(['ok' => true]);

        $this->assertDatabaseMissing('ged_folder_permissions', ['id' => $perm->id], 'tenant');
    }

    // =========================================================================
    // Interface gouvernance admin (AdminGedController)
    // =========================================================================

    public function test_agent_ne_peut_pas_acceder_gouvernance(): void
    {
        $agent = $this->makeUser(UserRole::USER);

        $this->actingAs($agent)
            ->get(route('admin.ged.index'))
            ->assertForbidden();
    }

    public function test_dgs_peut_acceder_gouvernance(): void
    {
        $dgs = $this->makeUser(UserRole::DGS);

        $this->actingAs($dgs)
            ->get(route('admin.ged.index'))
            ->assertOk();
    }

    public function test_transfert_propriete_deplace_dossiers_et_documents(): void
    {
        $dgs = $this->makeUser(UserRole::DGS);
        $from = $this->makeUser(UserRole::USER);
        $to = $this->makeUser(UserRole::USER);

        $folder = $this->makeFolder(['created_by' => $from->id]);
        $doc = $this->makeDoc($folder, $from);

        $this->actingAs($dgs)
            ->post(route('admin.ged.transfer-ownership'), [
                'from_user_id' => $from->id,
                'to_user_id' => $to->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('ged_folders', [
            'id' => $folder->id,
            'created_by' => $to->id,
        ], 'tenant');

        $this->assertDatabaseHas('ged_documents', [
            'id' => $doc->id,
            'created_by' => $to->id,
        ], 'tenant');
    }

    public function test_transfert_source_egale_cible_invalide(): void
    {
        $dgs = $this->makeUser(UserRole::DGS);
        $user = $this->makeUser(UserRole::USER);

        $this->actingAs($dgs)
            ->post(route('admin.ged.transfer-ownership'), [
                'from_user_id' => $user->id,
                'to_user_id' => $user->id,
            ])
            ->assertSessionHasErrors('to_user_id');
    }
}
