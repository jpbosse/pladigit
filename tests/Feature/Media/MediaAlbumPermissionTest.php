<?php

namespace Tests\Feature\Media;

use App\Enums\UserRole;
use App\Models\Tenant\MediaAlbum;
use App\Models\Tenant\Share;
use App\Models\Tenant\User;
use Tests\TestCase;

/**
 * Tests fonctionnels des droits par album (système Share générique).
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

        $this->assertDatabaseHas('shares', [
            'shareable_type'   => 'media_album',
            'shareable_id'     => $album->id,
            'shared_with_type' => 'role',
            'shared_with_role' => 'resp_service',
            'can_view'         => 1,
            'can_download'     => 1,
            'can_manage'       => 0,
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

        Share::create([
            'shareable_type'   => 'media_album',
            'shareable_id'     => $album->id,
            'shared_with_type' => 'role',
            'shared_with_role' => UserRole::USER->value,
            'can_view'         => true,
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
        Share::create([
            'shareable_type'   => 'media_album',
            'shareable_id'     => $album->id,
            'shared_with_type' => 'role',
            'shared_with_role' => UserRole::USER->value,
            'can_view'         => false,
        ]);

        // Override user individuel : can_view = true
        Share::create([
            'shareable_type'   => 'media_album',
            'shareable_id'     => $album->id,
            'shared_with_type' => 'user',
            'shared_with_id'   => $viewer->id,
            'can_view'         => true,
        ]);

        $this->actingAs($viewer);

        $this->get(route('media.albums.show', $album))
            ->assertOk();
    }

    public function test_ajout_partage_utilisateur(): void
    {
        $owner  = User::factory()->create();
        $target = User::factory()->create();

        $album = MediaAlbum::create([
            'name'       => 'Album Partage Store',
            'visibility' => 'restricted',
            'created_by' => $owner->id,
        ]);

        $this->actingAs($owner);

        $this->post(route('media.albums.permissions.store', $album), [
            'shared_with_type' => 'user',
            'shared_with_id'   => $target->id,
            'can_view'         => '1',
            'can_download'     => '1',
        ])->assertRedirect(route('media.albums.permissions.edit', $album));

        $this->assertDatabaseHas('shares', [
            'shareable_type'   => 'media_album',
            'shareable_id'     => $album->id,
            'shared_with_type' => 'user',
            'shared_with_id'   => $target->id,
            'can_view'         => 1,
            'can_download'     => 1,
            'can_manage'       => 0,
        ], 'tenant');
    }

    public function test_suppression_partage(): void
    {
        $owner  = User::factory()->create();
        $target = User::factory()->create();

        $album = MediaAlbum::create([
            'name'       => 'Album Partage Delete',
            'visibility' => 'restricted',
            'created_by' => $owner->id,
        ]);

        $share = Share::create([
            'shareable_type'   => 'media_album',
            'shareable_id'     => $album->id,
            'shared_with_type' => 'user',
            'shared_with_id'   => $target->id,
            'can_view'         => true,
        ]);

        $this->actingAs($owner);

        $this->delete(route('media.albums.permissions.destroy', [$album, $share]))
            ->assertRedirect(route('media.albums.permissions.edit', $album));

        $this->assertDatabaseMissing('shares', ['id' => $share->id], 'tenant');
    }
}
