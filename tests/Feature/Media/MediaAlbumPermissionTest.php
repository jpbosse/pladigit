<?php

namespace Tests\Feature\Media;

use App\Enums\UserRole;
use App\Models\Tenant\MediaAlbum;
use App\Models\Tenant\MediaAlbumPermission;
use App\Models\Tenant\MediaAlbumUserPermission;
use App\Models\Tenant\User;
use Tests\TestCase;

/**
 * Tests fonctionnels des droits par album.
 *
 * Couverture :
 *   - Page permissions accessible au gestionnaire
 *   - Page permissions interdite aux autres
 *   - Droits par rôle : enregistrement et résolution
 *   - Override utilisateur : prioritaire sur le rôle
 *   - Suppression d'un override
 *   - Admin/DGS toujours autorisés (before())
 */
class MediaAlbumPermissionTest extends TestCase
{
    // =========================================================================
    // Accès à la page permissions
    // =========================================================================

    public function test_le_createur_peut_acceder_a_la_page_permissions(): void
    {
        $user  = User::factory()->create();
        $album = MediaAlbum::create([
            'name'       => 'Album Test',
            'visibility' => 'restricted',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user);

        $this->get(route('media.albums.permissions.edit', $album))
            ->assertOk()
            ->assertSee('Droits par rôle');
    }

    public function test_un_autre_utilisateur_ne_peut_pas_acceder_aux_permissions(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $album = MediaAlbum::create([
            'name'       => 'Album Privé',
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
        $dgs   = User::factory()->create(['role' => UserRole::DGS->value]);

        $album = MediaAlbum::create([
            'name'       => 'Album quelconque',
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
        $user  = User::factory()->create();
        $album = MediaAlbum::create([
            'name'       => 'Album Rôles',
            'visibility' => 'restricted',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user);

        $this->put(route('media.albums.permissions.roles', $album), [
            'roles' => [
                'resp_service' => ['can_view' => '1', 'can_download' => '1'],
                'user'         => ['can_view' => '1'],
            ],
        ])->assertRedirect(route('media.albums.permissions.edit', $album));

        $this->assertDatabaseHas('media_album_permissions', [
            'album_id'     => $album->id,
            'role'         => 'resp_service',
            'can_view'     => 1,
            'can_download' => 1,
            'can_manage'   => 0,
        ], 'tenant');

        $this->assertDatabaseHas('media_album_permissions', [
            'album_id'   => $album->id,
            'role'       => 'user',
            'can_view'   => 1,
            'can_manage' => 0,
        ], 'tenant');
    }

    public function test_un_utilisateur_avec_droit_role_peut_voir_lalbum(): void
    {
        $owner  = User::factory()->create();
        $viewer = User::factory()->create(['role' => UserRole::USER->value]);

        $album = MediaAlbum::create([
            'name'       => 'Album Restreint',
            'visibility' => 'restricted',
            'created_by' => $owner->id,
        ]);

        // Donner can_view au rôle user
        MediaAlbumPermission::create([
            'album_id'     => $album->id,
            'role'         => UserRole::USER->value,
            'can_view'     => true,
            'can_download' => false,
            'can_manage'   => false,
        ]);

        $this->actingAs($viewer);

        $this->get(route('media.albums.show', $album))
            ->assertOk();
    }

    public function test_un_utilisateur_sans_droit_ne_peut_pas_voir_lalbum_restreint(): void
    {
        $owner  = User::factory()->create();
        $viewer = User::factory()->create(['role' => UserRole::USER->value]);

        $album = MediaAlbum::create([
            'name'       => 'Album Fermé',
            'visibility' => 'restricted',
            'created_by' => $owner->id,
        ]);

        // Pas de permission pour le rôle user

        $this->actingAs($viewer);

        $this->get(route('media.albums.show', $album))
            ->assertForbidden();
    }

    // =========================================================================
    // Override utilisateur
    // =========================================================================

    public function test_override_utilisateur_prioritaire_sur_le_role(): void
    {
        $owner  = User::factory()->create();
        $viewer = User::factory()->create(['role' => UserRole::USER->value]);

        $album = MediaAlbum::create([
            'name'       => 'Album Override',
            'visibility' => 'restricted',
            'created_by' => $owner->id,
        ]);

        // Rôle user : pas de droits
        MediaAlbumPermission::create([
            'album_id' => $album->id,
            'role'     => UserRole::USER->value,
            'can_view' => false,
        ]);

        // Override user individuel : can_view = true
        MediaAlbumUserPermission::create([
            'album_id' => $album->id,
            'user_id'  => $viewer->id,
            'can_view' => true,
        ]);

        $this->actingAs($viewer);

        // L'override donne accès malgré le rôle bloqué
        $this->get(route('media.albums.show', $album))
            ->assertOk();
    }

    public function test_ajout_override_utilisateur(): void
    {
        $owner  = User::factory()->create();
        $target = User::factory()->create();

        $album = MediaAlbum::create([
            'name'       => 'Album Override Store',
            'visibility' => 'restricted',
            'created_by' => $owner->id,
        ]);

        $this->actingAs($owner);

        $this->post(route('media.albums.permissions.user.store', $album), [
            'user_id'      => $target->id,
            'can_view'     => '1',
            'can_download' => '1',
        ])->assertRedirect(route('media.albums.permissions.edit', $album));

        $this->assertDatabaseHas('media_album_user_permissions', [
            'album_id'     => $album->id,
            'user_id'      => $target->id,
            'can_view'     => 1,
            'can_download' => 1,
            'can_manage'   => 0,
        ], 'tenant');
    }

    public function test_suppression_override_utilisateur(): void
    {
        $owner  = User::factory()->create();
        $target = User::factory()->create();

        $album = MediaAlbum::create([
            'name'       => 'Album Override Delete',
            'visibility' => 'restricted',
            'created_by' => $owner->id,
        ]);

        $perm = MediaAlbumUserPermission::create([
            'album_id' => $album->id,
            'user_id'  => $target->id,
            'can_view' => true,
        ]);

        $this->actingAs($owner);

        $this->delete(route('media.albums.permissions.user.destroy', [$album, $perm]))
            ->assertRedirect(route('media.albums.permissions.edit', $album));

        $this->assertDatabaseMissing('media_album_user_permissions', [
            'id' => $perm->id,
        ], 'tenant');
    }
}
