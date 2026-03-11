<?php

namespace Tests\Feature\Media;

use App\Enums\AlbumPermissionLevel;
use App\Enums\UserRole;
use App\Models\Tenant\AlbumPermission;
use App\Models\Tenant\AlbumUserPermission;
use App\Models\Tenant\MediaAlbum;
use App\Models\Tenant\User;
use Tests\TestCase;

/**
 * Tests fonctionnels des droits par album (système AlbumPermission).
 */
class MediaAlbumPermissionTest extends TestCase
{
    // =========================================================================
    // Accès à la page permissions
    // =========================================================================

    public function test_le_createur_peut_acceder_a_la_page_permissions(): void
    {
        $user = User::factory()->create();
        $album = MediaAlbum::create([
            'name' => 'Album Test',
            'visibility' => 'restricted',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user);

        $this->get(route('media.albums.permissions.edit', $album))
            ->assertOk()
            ->assertSee('Par rôle');
    }

    public function test_un_autre_utilisateur_ne_peut_pas_acceder_aux_permissions(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $album = MediaAlbum::create([
            'name' => 'Album Privé',
            'visibility' => 'restricted',
            'created_by' => $owner->id,
        ]);

        $this->actingAs($other);

        $this->get(route('media.albums.permissions.edit', $album))
            ->assertForbidden();
    }

    public function test_un_dgs_peut_acceder_aux_permissions_de_nimporte_quel_album(): void
    {
        $owner = User::factory()->create();
        $dgs = User::factory()->create(['role' => UserRole::DGS->value]);
        $album = MediaAlbum::create([
            'name' => 'Album quelconque',
            'visibility' => 'restricted',
            'created_by' => $owner->id,
        ]);

        $this->actingAs($dgs);

        $this->get(route('media.albums.permissions.edit', $album))
            ->assertOk();
    }

    // =========================================================================
    // Droits par rôle
    // =========================================================================

    public function test_enregistrement_des_droits_par_role(): void
    {
        $user = User::factory()->create();
        $album = MediaAlbum::create([
            'name' => 'Album Rôles',
            'visibility' => 'restricted',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user);

        $this->post(route('media.albums.permissions.store-subject', $album), [
            'subject_type' => 'role',
            'subject_role' => 'resp_service',
            'level' => 'view',
        ])->assertRedirect();

        $this->assertDatabaseHas('album_permissions', [
            'album_id' => $album->id,
            'subject_type' => 'role',
            'subject_role' => 'resp_service',
            'level' => 'view',
        ], 'tenant');
    }

    public function test_un_utilisateur_avec_droit_role_peut_voir_lalbum(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create(['role' => UserRole::USER->value]);
        $album = MediaAlbum::create([
            'name' => 'Album Restreint',
            'visibility' => 'restricted',
            'created_by' => $owner->id,
        ]);

        AlbumPermission::create([
            'album_id' => $album->id,
            'subject_type' => 'role',
            'subject_role' => UserRole::USER->value,
            'level' => AlbumPermissionLevel::View,
        ]);

        $this->actingAs($viewer);

        $this->get(route('media.albums.show', $album))
            ->assertOk();
    }

    public function test_un_utilisateur_sans_droit_ne_peut_pas_voir_lalbum_restreint(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create(['role' => UserRole::USER->value]);
        $album = MediaAlbum::create([
            'name' => 'Album Fermé',
            'visibility' => 'restricted',
            'created_by' => $owner->id,
        ]);

        $this->actingAs($viewer);

        $this->get(route('media.albums.show', $album))
            ->assertForbidden();
    }

    // =========================================================================
    // Override utilisateur
    // =========================================================================

    public function test_override_utilisateur_prioritaire_sur_le_role(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create(['role' => UserRole::USER->value]);
        $album = MediaAlbum::create([
            'name' => 'Album Override',
            'visibility' => 'restricted',
            'created_by' => $owner->id,
        ]);

        // Rôle user : aucun droit
        AlbumPermission::create([
            'album_id' => $album->id,
            'subject_type' => 'role',
            'subject_role' => UserRole::USER->value,
            'level' => AlbumPermissionLevel::None,
        ]);

        // Override individuel : view
        AlbumUserPermission::create([
            'album_id' => $album->id,
            'user_id' => $viewer->id,
            'level' => AlbumPermissionLevel::View,
        ]);

        $this->actingAs($viewer);

        $this->get(route('media.albums.show', $album))
            ->assertOk();
    }

    // =========================================================================
    // Ajout / suppression permission utilisateur
    // =========================================================================

    public function test_ajout_partage_utilisateur(): void
    {
        $owner = User::factory()->create();
        $target = User::factory()->create();
        $album = MediaAlbum::create([
            'name' => 'Album Partage Store',
            'visibility' => 'restricted',
            'created_by' => $owner->id,
        ]);

        $this->actingAs($owner);

        $response = $this->post(route('media.albums.permissions.store-user', $album), [
            'user_id' => $target->id,
            'level' => 'download',
        ]);
        $response->assertRedirect(route('media.albums.permissions.edit', $album));

        $this->assertDatabaseHas('album_user_permissions', [
            'album_id' => $album->id,
            'user_id' => $target->id,
            'level' => 'download',
        ], 'tenant');
    }

    public function test_suppression_partage(): void
    {
        $owner = User::factory()->create();
        $target = User::factory()->create();
        $album = MediaAlbum::create([
            'name' => 'Album Partage Delete',
            'visibility' => 'restricted',
            'created_by' => $owner->id,
        ]);

        $perm = AlbumUserPermission::create([
            'album_id' => $album->id,
            'user_id' => $target->id,
            'level' => AlbumPermissionLevel::View,
        ]);

        $this->actingAs($owner);

        $this->delete(route('media.albums.permissions.destroy-user', [$album, $perm]))
            ->assertRedirect(route('media.albums.permissions.edit', $album));

        $this->assertDatabaseMissing('album_user_permissions', ['id' => $perm->id], 'tenant');
    }
}
