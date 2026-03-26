<?php

namespace Tests\Feature\Ged;

use App\Enums\UserRole;
use App\Models\Tenant\GedDocument;
use App\Models\Tenant\GedFolder;
use App\Models\Tenant\User;
use Tests\TestCase;

/**
 * Tests fonctionnels — GED, gestion des dossiers.
 *
 * Couverture :
 *   - CRUD dossiers (création racine, sous-dossier, renommer, supprimer)
 *   - Protection : refus suppression dossier non vide
 *   - Déplacement et protection anti-boucle circulaire
 *   - Visibilité des dossiers privés
 *   - Lazy-load children (API JSON)
 *   - buildPath() après déplacement
 */
class GedFolderTest extends TestCase
{
    // ── Helpers ──────────────────────────────────────────────

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::ADMIN->value]);
    }

    private function agent(): User
    {
        return User::factory()->create(['role' => UserRole::USER->value]);
    }

    private function makeFolder(array $attrs = []): GedFolder
    {
        $user = $attrs['created_by'] ?? $this->admin()->id;

        return GedFolder::create(array_merge([
            'name' => 'Dossier Test',
            'slug' => 'dossier-test',
            'path' => '/dossier-test',
            'parent_id' => null,
            'is_private' => false,
            'created_by' => $user,
        ], $attrs));
    }

    // =========================================================================
    // Création
    // =========================================================================

    public function test_creer_un_dossier_racine(): void
    {
        $user = $this->admin();
        $this->actingAs($user);

        $response = $this->postJson(route('ged.folders.store'), [
            'name' => 'Ressources Humaines',
        ]);

        $response->assertOk()->assertJsonFragment(['ok' => true]);

        $this->assertDatabaseHas('ged_folders', [
            'name' => 'Ressources Humaines',
            'slug' => 'ressources-humaines',
            'path' => '/ressources-humaines',
            'parent_id' => null,
            'created_by' => $user->id,
        ], 'tenant');
    }

    public function test_creer_un_sous_dossier(): void
    {
        $user = $this->admin();
        $parent = $this->makeFolder(['name' => 'RH', 'slug' => 'rh', 'path' => '/rh', 'created_by' => $user->id]);

        $this->actingAs($user);

        $response = $this->postJson(route('ged.folders.store'), [
            'name' => 'Contrats',
            'parent_id' => $parent->id,
        ]);

        $response->assertOk()->assertJsonFragment(['ok' => true]);

        $this->assertDatabaseHas('ged_folders', [
            'name' => 'Contrats',
            'slug' => 'contrats',
            'path' => '/rh/contrats',
            'parent_id' => $parent->id,
        ], 'tenant');
    }

    public function test_la_creation_echoue_sans_nom(): void
    {
        $this->actingAs($this->admin());

        $response = $this->postJson(route('ged.folders.store'), ['name' => '']);

        $response->assertUnprocessable();
    }

    // =========================================================================
    // Renommer
    // =========================================================================

    public function test_renommer_un_dossier(): void
    {
        $user = $this->admin();
        $folder = $this->makeFolder(['name' => 'Ancien Nom', 'slug' => 'ancien-nom', 'path' => '/ancien-nom', 'created_by' => $user->id]);

        $this->actingAs($user);

        $response = $this->putJson(route('ged.folders.update', $folder), [
            'name' => 'Nouveau Nom',
        ]);

        $response->assertOk()->assertJsonFragment(['ok' => true]);

        $this->assertDatabaseHas('ged_folders', [
            'id' => $folder->id,
            'name' => 'Nouveau Nom',
            'slug' => 'nouveau-nom',
            'path' => '/nouveau-nom',
        ], 'tenant');
    }

    // =========================================================================
    // Suppression
    // =========================================================================

    public function test_supprimer_un_dossier_vide(): void
    {
        $user = $this->admin();
        $folder = $this->makeFolder(['created_by' => $user->id]);

        $this->actingAs($user);

        $response = $this->deleteJson(route('ged.folders.destroy', $folder));

        $response->assertOk()->assertJsonFragment(['ok' => true]);

        $this->assertSoftDeleted('ged_folders', ['id' => $folder->id], 'tenant');
    }

    public function test_refuser_suppression_dossier_avec_sous_dossiers(): void
    {
        $user = $this->admin();
        $parent = $this->makeFolder(['name' => 'Parent', 'slug' => 'parent', 'path' => '/parent', 'created_by' => $user->id]);

        GedFolder::create([
            'name' => 'Enfant',
            'slug' => 'enfant',
            'path' => '/parent/enfant',
            'parent_id' => $parent->id,
            'is_private' => false,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user);

        $response = $this->deleteJson(route('ged.folders.destroy', $parent));

        $response->assertUnprocessable()->assertJsonFragment(['error' => 'Impossible de supprimer un dossier non vide.']);

        $this->assertDatabaseHas('ged_folders', ['id' => $parent->id, 'deleted_at' => null], 'tenant');
    }

    public function test_refuser_suppression_dossier_avec_documents(): void
    {
        $user = $this->admin();
        $folder = $this->makeFolder(['created_by' => $user->id]);

        GedDocument::create([
            'folder_id' => $folder->id,
            'name' => 'doc.pdf',
            'disk_path' => '/fake/doc.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 10240,
            'current_version' => 1,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user);

        $response = $this->deleteJson(route('ged.folders.destroy', $folder));

        $response->assertUnprocessable();
    }

    // =========================================================================
    // Déplacement
    // =========================================================================

    public function test_deplacer_un_dossier_vers_un_nouveau_parent(): void
    {
        $user = $this->admin();
        $folder = $this->makeFolder(['name' => 'Mobile', 'slug' => 'mobile', 'path' => '/mobile', 'created_by' => $user->id]);
        $newParent = $this->makeFolder(['name' => 'Projets', 'slug' => 'projets', 'path' => '/projets', 'created_by' => $user->id]);

        $this->actingAs($user);

        $response = $this->postJson(route('ged.folders.move', $folder), [
            'parent_id' => $newParent->id,
        ]);

        $response->assertOk()->assertJsonFragment(['ok' => true]);

        $this->assertDatabaseHas('ged_folders', [
            'id' => $folder->id,
            'parent_id' => $newParent->id,
            'path' => '/projets/mobile',
        ], 'tenant');
    }

    public function test_refuser_deplacement_si_boucle_circulaire(): void
    {
        $user = $this->admin();
        $parent = $this->makeFolder(['name' => 'Parent', 'slug' => 'parent', 'path' => '/parent', 'created_by' => $user->id]);
        $child = GedFolder::create([
            'name' => 'Enfant',
            'slug' => 'enfant',
            'path' => '/parent/enfant',
            'parent_id' => $parent->id,
            'is_private' => false,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user);

        // Tenter de déplacer Parent DANS Enfant → boucle
        $response = $this->postJson(route('ged.folders.move', $parent), [
            'parent_id' => $child->id,
        ]);

        $response->assertUnprocessable();
    }

    public function test_refuser_deplacement_sur_soi_meme(): void
    {
        $user = $this->admin();
        $folder = $this->makeFolder(['created_by' => $user->id]);

        $this->actingAs($user);

        $response = $this->postJson(route('ged.folders.move', $folder), [
            'parent_id' => $folder->id,
        ]);

        $response->assertUnprocessable();
    }

    // =========================================================================
    // Visibilité des dossiers privés
    // =========================================================================

    public function test_dossier_prive_non_visible_pour_autre_utilisateur(): void
    {
        $owner = $this->admin();
        $other = $this->agent();

        GedFolder::create([
            'name' => 'Confidentiel',
            'slug' => 'confidentiel',
            'path' => '/confidentiel',
            'parent_id' => null,
            'is_private' => true,
            'created_by' => $owner->id,
        ]);

        $this->actingAs($other);

        $response = $this->get(route('ged.index'));
        $response->assertOk()->assertDontSee('Confidentiel');
    }

    public function test_dossier_prive_visible_pour_son_createur(): void
    {
        $owner = $this->agent();

        GedFolder::create([
            'name' => 'MonDossierSecret',
            'slug' => 'mon-dossier-secret',
            'path' => '/mon-dossier-secret',
            'parent_id' => null,
            'is_private' => true,
            'created_by' => $owner->id,
        ]);

        $this->actingAs($owner);

        $response = $this->get(route('ged.index'));
        $response->assertOk()->assertSee('MonDossierSecret');
    }

    public function test_dossier_prive_visible_pour_admin(): void
    {
        $agent = $this->agent();
        $admin = $this->admin();

        GedFolder::create([
            'name' => 'DossierAdmin',
            'slug' => 'dossier-admin',
            'path' => '/dossier-admin',
            'parent_id' => null,
            'is_private' => true,
            'created_by' => $agent->id,
        ]);

        $this->actingAs($admin);

        $response = $this->get(route('ged.index'));
        $response->assertOk()->assertSee('DossierAdmin');
    }

    // =========================================================================
    // Lazy-load children (API)
    // =========================================================================

    public function test_lazy_load_children_retourne_json_correct(): void
    {
        $user = $this->admin();
        $parent = $this->makeFolder(['name' => 'Parent', 'slug' => 'parent', 'path' => '/parent', 'created_by' => $user->id]);

        GedFolder::create([
            'name' => 'Enfant A',
            'slug' => 'enfant-a',
            'path' => '/parent/enfant-a',
            'parent_id' => $parent->id,
            'is_private' => false,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user);

        $response = $this->getJson(route('ged.folders.children', $parent));

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['name' => 'Enfant A', 'slug' => 'enfant-a']);
    }

    // =========================================================================
    // buildPath() / propagation
    // =========================================================================

    public function test_build_path_correct_apres_deplacement(): void
    {
        $user = $this->admin();
        $parent = $this->makeFolder(['name' => 'A', 'slug' => 'a', 'path' => '/a', 'created_by' => $user->id]);
        $child = GedFolder::create([
            'name' => 'B',
            'slug' => 'b',
            'path' => '/b',
            'parent_id' => null,
            'is_private' => false,
            'created_by' => $user->id,
        ]);
        // Sous-enfant de B
        $grandchild = GedFolder::create([
            'name' => 'C',
            'slug' => 'c',
            'path' => '/b/c',
            'parent_id' => $child->id,
            'is_private' => false,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user);

        // Déplacer B sous A → B devient /a/b, C devient /a/b/c
        $this->postJson(route('ged.folders.move', $child), ['parent_id' => $parent->id]);

        $this->assertDatabaseHas('ged_folders', ['id' => $child->id, 'path' => '/a/b'], 'tenant');
        $this->assertDatabaseHas('ged_folders', ['id' => $grandchild->id, 'path' => '/a/b/c'], 'tenant');
    }

    public function test_acces_index_ged_ok(): void
    {
        $this->actingAs($this->admin());

        $response = $this->get(route('ged.index'));

        $response->assertOk();
    }
}
