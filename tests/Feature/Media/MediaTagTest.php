<?php

namespace Tests\Feature\Media;

use App\Models\Tenant\MediaAlbum;
use App\Models\Tenant\MediaItem;
use App\Models\Tenant\Tag;
use App\Models\Tenant\User;
use Tests\TestCase;

/**
 * Tests des tags manuels sur les médias.
 *
 * Couverture :
 *   - Ajout d'un tag (crée si inexistant, attache)
 *   - Ajout d'un tag existant (idempotent)
 *   - Suppression d'un tag
 *   - Suppression d'un tag orphelin (supprimé de la table)
 *   - Tag conservé si partagé avec d'autres items
 *   - Autocomplete suggest
 *   - Filtre par tag dans l'album
 *   - Seul un manager peut ajouter/supprimer un tag
 *   - Normalisation en minuscules
 */
class MediaTagTest extends TestCase
{
    private User $admin;

    private MediaAlbum $album;

    private MediaItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->album = MediaAlbum::factory()->public()->create(['created_by' => $this->admin->id]);
        $this->item = MediaItem::factory()->create(['album_id' => $this->album->id]);
        $this->actingAs($this->admin);
    }

    // =========================================================================
    // Ajout de tags
    // =========================================================================

    public function test_ajout_tag_cree_et_attache(): void
    {
        $this->postJson(route('media.items.tags.store', $this->item), ['name' => 'nature'])
            ->assertOk()
            ->assertJsonFragment(['name' => 'nature']);

        $this->assertDatabaseHas('media_tags', ['name' => 'nature'], 'tenant');
        $this->assertTrue($this->item->fresh()->tags->contains('name', 'nature'));
    }

    public function test_ajout_tag_normalise_en_minuscules(): void
    {
        $this->postJson(route('media.items.tags.store', $this->item), ['name' => 'ÉTÉ 2024'])
            ->assertOk()
            ->assertJsonFragment(['name' => 'été 2024']);
    }

    public function test_ajout_tag_idempotent(): void
    {
        $this->postJson(route('media.items.tags.store', $this->item), ['name' => 'doublons']);
        $this->postJson(route('media.items.tags.store', $this->item), ['name' => 'doublons']);

        $this->assertSame(1, $this->item->fresh()->tags->count());
    }

    public function test_ajout_tag_reutilise_existant(): void
    {
        $tag = Tag::create(['name' => 'réunion']);

        $this->postJson(route('media.items.tags.store', $this->item), ['name' => 'réunion'])
            ->assertOk()
            ->assertJsonFragment(['id' => $tag->id]);

        $this->assertDatabaseCount('media_tags', 1, 'tenant');
    }

    public function test_ajout_tag_refuse_nom_vide(): void
    {
        $this->postJson(route('media.items.tags.store', $this->item), ['name' => ''])
            ->assertStatus(422);
    }

    public function test_ajout_tag_refuse_nom_trop_long(): void
    {
        $this->postJson(route('media.items.tags.store', $this->item), ['name' => str_repeat('a', 51)])
            ->assertStatus(422);
    }

    // =========================================================================
    // Suppression de tags
    // =========================================================================

    public function test_suppression_tag_detache(): void
    {
        $tag = Tag::create(['name' => 'paysage']);
        $this->item->tags()->attach($tag->id);

        $this->deleteJson(route('media.items.tags.destroy', [$this->item, $tag]))
            ->assertOk();

        $this->assertFalse($this->item->fresh()->tags->contains('id', $tag->id));
    }

    public function test_suppression_tag_orphelin_le_supprime(): void
    {
        $tag = Tag::create(['name' => 'ephemere']);
        $this->item->tags()->attach($tag->id);

        $this->deleteJson(route('media.items.tags.destroy', [$this->item, $tag]));

        $this->assertDatabaseMissing('media_tags', ['name' => 'ephemere'], 'tenant');
    }

    public function test_suppression_tag_partage_le_conserve(): void
    {
        $other = MediaItem::factory()->create(['album_id' => $this->album->id]);
        $tag = Tag::create(['name' => 'partagé']);
        $this->item->tags()->attach($tag->id);
        $other->tags()->attach($tag->id);

        $this->deleteJson(route('media.items.tags.destroy', [$this->item, $tag]));

        $this->assertDatabaseHas('media_tags', ['name' => 'partagé'], 'tenant');
    }

    // =========================================================================
    // Autorisations
    // =========================================================================

    public function test_utilisateur_non_manager_ne_peut_pas_ajouter(): void
    {
        $viewer = User::factory()->create(['role' => 'user']);
        $this->actingAs($viewer);

        $this->postJson(route('media.items.tags.store', $this->item), ['name' => 'test'])
            ->assertForbidden();
    }

    // =========================================================================
    // Autocomplete
    // =========================================================================

    public function test_suggest_retourne_tags_correspondants(): void
    {
        Tag::create(['name' => 'vacances']);
        Tag::create(['name' => 'vintage']);
        Tag::create(['name' => 'paysage']);

        $this->get(route('media.tags.suggest', ['q' => 'va']))
            ->assertOk()
            ->assertJsonFragment(['vacances'])
            ->assertJsonMissing(['paysage']);
    }

    public function test_suggest_retourne_tous_si_q_vide(): void
    {
        Tag::create(['name' => 'alpha']);
        Tag::create(['name' => 'beta']);

        $this->get(route('media.tags.suggest'))
            ->assertOk()
            ->assertJsonCount(2);
    }

    // =========================================================================
    // Filtre par tag dans l'album
    // =========================================================================

    public function test_filtre_par_tag_retourne_items_corrects(): void
    {
        $tag = Tag::create(['name' => 'mer']);
        $this->item->tags()->attach($tag->id);
        $other = MediaItem::factory()->create(['album_id' => $this->album->id]);

        $response = $this->get(route('media.albums.show', $this->album).'?tag_id='.$tag->id);

        $response->assertOk();
        $ids = $response->viewData('items')->pluck('id')->toArray();
        $this->assertContains($this->item->id, $ids);
        $this->assertNotContains($other->id, $ids);
    }

    // =========================================================================
    // Vue items/show — tags chargés
    // =========================================================================

    public function test_page_item_affiche_tags(): void
    {
        $tag = Tag::create(['name' => 'montagne']);
        $this->item->tags()->attach($tag->id);

        $this->get(route('media.items.show', [$this->album, $this->item]))
            ->assertOk()
            ->assertSee('montagne');
    }
}
