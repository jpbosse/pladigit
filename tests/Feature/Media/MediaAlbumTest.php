<?php

namespace Tests\Feature\Media;

use App\Models\Tenant\MediaAlbum;
use App\Models\Tenant\User;
use Tests\TestCase;

/**
 * Tests fonctionnels du module Photothèque — Albums.
 *
 * Couverture :
 *   - CRUD albums (création, lecture, modification, suppression)
 *   - Visibilité selon le rôle (public / restricted / private)
 *   - Isolation : un album privé n'est pas visible par un autre utilisateur
 *   - Soft delete : l'album supprimé n'apparaît plus dans la liste
 */
class MediaAlbumTest extends TestCase
{
    // =========================================================================
    // Création
    // =========================================================================

    public function test_un_utilisateur_peut_creer_un_album(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->post(route('media.albums.store'), [
            'name' => 'Album Fête de la Musique 2026',
            'description' => 'Photos de la fête.',
            'visibility' => 'restricted',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('media_albums', [
            'name' => 'Album Fête de la Musique 2026',
            'created_by' => $user->id,
            'visibility' => 'restricted',
        ], 'tenant');
    }

    public function test_la_creation_echoue_sans_nom(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->post(route('media.albums.store'), [
            'name' => '',
            'visibility' => 'public',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_la_creation_echoue_avec_visibilite_invalide(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->post(route('media.albums.store'), [
            'name' => 'Test',
            'visibility' => 'invalide',
        ]);

        $response->assertSessionHasErrors('visibility');
    }

    // =========================================================================
    // Lecture / liste
    // =========================================================================

    public function test_la_liste_des_albums_est_accessible(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        MediaAlbum::create([
            'name' => 'Album Public Test',
            'visibility' => 'public',
            'created_by' => $user->id,
        ]);

        $response = $this->get(route('media.albums.index'));

        $response->assertOk();
        $response->assertSee('Album Public Test');
    }

    public function test_un_album_public_est_visible_par_tout_utilisateur(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $album = MediaAlbum::create([
            'name' => 'Album Public',
            'visibility' => 'public',
            'created_by' => $owner->id,
        ]);

        $this->actingAs($other);

        $this->get(route('media.albums.show', $album))
            ->assertOk()
            ->assertSee('Album Public');
    }

    public function test_un_album_prive_nest_pas_visible_par_un_autre_utilisateur(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $album = MediaAlbum::create([
            'name' => 'Album Privé',
            'visibility' => 'private',
            'created_by' => $owner->id,
        ]);

        $this->actingAs($other);

        $this->get(route('media.albums.show', $album))
            ->assertForbidden();
    }

    public function test_un_album_prive_est_visible_par_son_createur(): void
    {
        $owner = User::factory()->create();

        $album = MediaAlbum::create([
            'name' => 'Mon Album Privé',
            'visibility' => 'private',
            'created_by' => $owner->id,
        ]);

        $this->actingAs($owner);

        $this->get(route('media.albums.show', $album))
            ->assertOk()
            ->assertSee('Mon Album Privé');
    }

    public function test_un_album_restreint_est_visible_par_les_membres(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $album = MediaAlbum::create([
            'name' => 'Album Restreint',
            'visibility' => 'restricted',
            'created_by' => $owner->id,
        ]);

        $this->actingAs($member);

        $this->get(route('media.albums.show', $album))
            ->assertOk();
    }

    // =========================================================================
    // Modification
    // =========================================================================

    public function test_un_utilisateur_peut_modifier_un_album(): void
    {
        $user = User::factory()->create();
        $album = MediaAlbum::create([
            'name' => 'Ancien Nom',
            'visibility' => 'restricted',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user);

        $this->put(route('media.albums.update', $album), [
            'name' => 'Nouveau Nom',
            'visibility' => 'public',
        ])->assertRedirect(route('media.albums.show', $album));

        $this->assertDatabaseHas('media_albums', [
            'id' => $album->id,
            'name' => 'Nouveau Nom',
            'visibility' => 'public',
        ], 'tenant');
    }

    // =========================================================================
    // Suppression
    // =========================================================================

    public function test_la_suppression_soft_delete_cache_lalbum(): void
    {
        $user = User::factory()->create();
        $album = MediaAlbum::create([
            'name' => 'Album à supprimer',
            'visibility' => 'restricted',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user);

        $this->delete(route('media.albums.destroy', $album))
            ->assertRedirect(route('media.albums.index'));

        // Soft delete : l'enregistrement existe toujours en base mais deleted_at est renseigné
        $this->assertSoftDeleted('media_albums', ['id' => $album->id], 'tenant');

        // L'album n'est plus récupérable via le scope par défaut
        $this->assertNull(MediaAlbum::find($album->id));
    }

    // =========================================================================
    // Authentification requise
    // =========================================================================

    public function test_un_visiteur_non_authentifie_est_redirige(): void
    {
        $this->get(route('media.albums.index'))
            ->assertRedirect(route('login'));
    }
}
