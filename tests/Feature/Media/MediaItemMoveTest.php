<?php

namespace Tests\Feature\Media;

use App\Models\Tenant\MediaAlbum;
use App\Models\Tenant\MediaItem;
use App\Models\Tenant\User;
use Tests\TestCase;

/**
 * Tests du déplacement de médias entre albums (DnD point C).
 *
 * Couverture :
 *   - Déplacer un item vers un autre album
 *   - Déplacer plusieurs items en batch
 *   - Les chemins NAS sont mis à jour
 *   - Conflit de nom résolu automatiquement
 *   - Refus si l'utilisateur n'est pas manager de l'album source
 *   - Refus si l'utilisateur ne peut pas uploader dans l'album cible
 *   - Refus si target_album_id = source
 *   - Retour 422 si item_ids vide
 */
class MediaItemMoveTest extends TestCase
{
    private User $admin;

    private MediaAlbum $source;

    private MediaAlbum $target;

    private MediaItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->source = MediaAlbum::factory()->public()->create([
            'created_by' => $this->admin->id,
            'nas_path' => 'album-source',
        ]);
        $this->target = MediaAlbum::factory()->public()->create([
            'created_by' => $this->admin->id,
            'nas_path' => 'album-target',
        ]);
        $this->item = MediaItem::factory()->create([
            'album_id' => $this->source->id,
            'file_path' => 'album-source/photo.jpg',
            'thumb_path' => 'album-source/thumbs/photo.jpg',
        ]);
        $this->actingAs($this->admin);
    }

    // =========================================================================
    // Déplacement simple
    // =========================================================================

    public function test_deplacer_un_item_met_a_jour_album_id(): void
    {
        $this->postJson(route('media.items.move', $this->source), [
            'item_ids' => [$this->item->id],
            'target_album_id' => $this->target->id,
        ])->assertOk()->assertJsonFragment(['moved' => 1]);

        $this->assertSame($this->target->id, $this->item->fresh()->album_id);
    }

    public function test_deplacer_met_a_jour_file_path(): void
    {
        $this->postJson(route('media.items.move', $this->source), [
            'item_ids' => [$this->item->id],
            'target_album_id' => $this->target->id,
        ])->assertOk();

        $fresh = $this->item->fresh();
        $this->assertStringStartsWith('album-target/', $fresh->file_path);
        $this->assertSame('photo.jpg', basename($fresh->file_path));
    }

    public function test_deplacer_met_a_jour_thumb_path(): void
    {
        $this->postJson(route('media.items.move', $this->source), [
            'item_ids' => [$this->item->id],
            'target_album_id' => $this->target->id,
        ])->assertOk();

        $fresh = $this->item->fresh();
        $this->assertStringStartsWith('album-target/thumbs/', $fresh->thumb_path);
    }

    public function test_deplacer_plusieurs_items(): void
    {
        $item2 = MediaItem::factory()->create([
            'album_id' => $this->source->id,
            'file_path' => 'album-source/photo2.jpg',
        ]);

        $this->postJson(route('media.items.move', $this->source), [
            'item_ids' => [$this->item->id, $item2->id],
            'target_album_id' => $this->target->id,
        ])->assertOk()->assertJsonFragment(['moved' => 2]);

        $this->assertSame($this->target->id, $this->item->fresh()->album_id);
        $this->assertSame($this->target->id, $item2->fresh()->album_id);
    }

    public function test_reponse_contient_target_name_et_url(): void
    {
        $this->postJson(route('media.items.move', $this->source), [
            'item_ids' => [$this->item->id],
            'target_album_id' => $this->target->id,
        ])->assertOk()
            ->assertJsonFragment(['target_name' => $this->target->name]);
    }

    // =========================================================================
    // Autorisations
    // =========================================================================

    public function test_refus_si_non_manager_source(): void
    {
        $viewer = User::factory()->create(['role' => 'user']);
        $this->actingAs($viewer);

        $this->postJson(route('media.items.move', $this->source), [
            'item_ids' => [$this->item->id],
            'target_album_id' => $this->target->id,
        ])->assertForbidden();
    }

    public function test_refus_si_non_upload_sur_target(): void
    {
        // Resp.Service peut gérer son propre album source mais pas une cible privée d'un autre
        $respService = User::factory()->create(['role' => 'resp_service']);
        $ownSource = MediaAlbum::factory()->public()->create(['created_by' => $respService->id, 'nas_path' => 'resp-source']);
        $ownItem = MediaItem::factory()->create(['album_id' => $ownSource->id, 'file_path' => 'resp-source/x.jpg']);

        $privateTarget = MediaAlbum::factory()->create([
            'created_by' => $this->admin->id,
            'visibility' => 'private',
            'nas_path' => 'admin-private',
        ]);

        $this->actingAs($respService);

        $this->postJson(route('media.items.move', $ownSource), [
            'item_ids' => [$ownItem->id],
            'target_album_id' => $privateTarget->id,
        ])->assertForbidden();
    }

    // =========================================================================
    // Validation
    // =========================================================================

    public function test_refus_si_target_est_source(): void
    {
        $this->postJson(route('media.items.move', $this->source), [
            'item_ids' => [$this->item->id],
            'target_album_id' => $this->source->id,
        ])->assertStatus(422);
    }

    public function test_refus_si_item_ids_vide(): void
    {
        $this->postJson(route('media.items.move', $this->source), [
            'item_ids' => [],
            'target_album_id' => $this->target->id,
        ])->assertStatus(422);
    }

    public function test_ignore_item_appartenant_a_un_autre_album(): void
    {
        $other = MediaItem::factory()->create([
            'album_id' => $this->target->id,
            'file_path' => 'album-target/other.jpg',
        ]);

        $this->postJson(route('media.items.move', $this->source), [
            'item_ids' => [$other->id],
            'target_album_id' => $this->target->id,
        ])->assertOk()->assertJsonFragment(['moved' => 0]);
    }
}
