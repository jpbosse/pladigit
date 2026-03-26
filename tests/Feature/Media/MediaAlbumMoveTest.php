<?php

namespace Tests\Feature\Media;

use App\Models\Tenant\MediaAlbum;
use App\Models\Tenant\MediaItem;
use App\Models\Tenant\User;
use Tests\TestCase;

/**
 * Tests du déplacement d'albums dans la hiérarchie (DnD point B).
 *
 * Couverture :
 *   - Déplacer un album sous un autre (nouveau parent)
 *   - Mettre un album à la racine (parent_id = null)
 *   - Les nas_path des descendants sont mis à jour
 *   - Les file_path + thumb_path des médias sont mis à jour
 *   - Refus si même position
 *   - Refus si boucle circulaire
 *   - Refus si parent = lui-même
 *   - Refus si non-manager
 */
class MediaAlbumMoveTest extends TestCase
{
    private User $admin;

    private MediaAlbum $albumA;   // racine  → nas: "album-a"

    private MediaAlbum $albumB;   // racine  → nas: "album-b"

    private MediaAlbum $childA1;  // enfant de A → nas: "album-a/sub1"

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);

        $this->albumA = MediaAlbum::factory()->public()->create([
            'created_by' => $this->admin->id,
            'nas_path' => 'album-a',
            'parent_id' => null,
        ]);

        $this->albumB = MediaAlbum::factory()->public()->create([
            'created_by' => $this->admin->id,
            'nas_path' => 'album-b',
            'parent_id' => null,
        ]);

        $this->childA1 = MediaAlbum::factory()->public()->create([
            'created_by' => $this->admin->id,
            'nas_path' => 'album-a/sub1',
            'parent_id' => $this->albumA->id,
        ]);

        $this->actingAs($this->admin);
    }

    // =========================================================================
    // Déplacement simple
    // =========================================================================

    public function test_deplacer_sous_un_autre_album(): void
    {
        $this->postJson(route('media.albums.move', $this->albumA), [
            'parent_id' => $this->albumB->id,
        ])->assertOk()->assertJsonFragment(['ok' => true]);

        $fresh = $this->albumA->fresh();
        $this->assertSame($this->albumB->id, $fresh->parent_id);
        $this->assertSame('album-b/album-a', $fresh->nas_path);
    }

    public function test_mettre_a_la_racine(): void
    {
        // Mettre childA1 à la racine
        $this->postJson(route('media.albums.move', $this->childA1), [
            'parent_id' => null,
        ])->assertOk();

        $fresh = $this->childA1->fresh();
        $this->assertNull($fresh->parent_id);
        $this->assertSame('sub1', $fresh->nas_path);
    }

    public function test_descendants_nas_path_mis_a_jour(): void
    {
        // childA1 est sous albumA — quand on déplace albumA sous albumB,
        // le nas_path de childA1 doit être mis à jour
        $this->postJson(route('media.albums.move', $this->albumA), [
            'parent_id' => $this->albumB->id,
        ])->assertOk();

        $freshChild = $this->childA1->fresh();
        $this->assertSame('album-b/album-a/sub1', $freshChild->nas_path);
    }

    public function test_file_path_medias_mis_a_jour(): void
    {
        $item = MediaItem::factory()->create([
            'album_id' => $this->albumA->id,
            'file_path' => 'album-a/photo.jpg',
            'thumb_path' => 'album-a/thumbs/photo.jpg',
        ]);

        $this->postJson(route('media.albums.move', $this->albumA), [
            'parent_id' => $this->albumB->id,
        ])->assertOk();

        $fresh = $item->fresh();
        $this->assertSame('album-b/album-a/photo.jpg', $fresh->file_path);
        $this->assertSame('album-b/album-a/thumbs/photo.jpg', $fresh->thumb_path);
    }

    public function test_medias_descendants_file_path_mis_a_jour(): void
    {
        $item = MediaItem::factory()->create([
            'album_id' => $this->childA1->id,
            'file_path' => 'album-a/sub1/photo.jpg',
            'thumb_path' => 'album-a/sub1/thumbs/photo.jpg',
        ]);

        $this->postJson(route('media.albums.move', $this->albumA), [
            'parent_id' => $this->albumB->id,
        ])->assertOk();

        $fresh = $item->fresh();
        $this->assertSame('album-b/album-a/sub1/photo.jpg', $fresh->file_path);
        $this->assertSame('album-b/album-a/sub1/thumbs/photo.jpg', $fresh->thumb_path);
    }

    public function test_meme_position_retourne_ok_sans_changement(): void
    {
        $this->postJson(route('media.albums.move', $this->childA1), [
            'parent_id' => $this->albumA->id,
        ])->assertOk()->assertJsonFragment(['message' => 'Aucun changement.']);
    }

    // =========================================================================
    // Validation
    // =========================================================================

    public function test_refus_boucle_circulaire(): void
    {
        $this->postJson(route('media.albums.move', $this->albumA), [
            'parent_id' => $this->childA1->id,
        ])->assertStatus(422);
    }

    public function test_refus_parent_lui_meme(): void
    {
        $this->postJson(route('media.albums.move', $this->albumA), [
            'parent_id' => $this->albumA->id,
        ])->assertStatus(422);
    }

    // =========================================================================
    // Autorisations
    // =========================================================================

    public function test_refus_si_non_manager(): void
    {
        $viewer = User::factory()->create(['role' => 'user']);
        $this->actingAs($viewer);

        $this->postJson(route('media.albums.move', $this->albumA), [
            'parent_id' => $this->albumB->id,
        ])->assertForbidden();
    }
}
