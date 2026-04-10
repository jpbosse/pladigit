<?php

namespace Tests\Feature\Media;

use App\Models\Tenant\MediaAlbum;
use App\Models\Tenant\MediaItem;
use App\Models\Tenant\User;
use Tests\TestCase;

/**
 * Tests de la vue complète des métadonnées EXIF.
 *
 * Couverture :
 *   - Page items/show : champs EXIF classiques affichés
 *   - Page items/show : nouveaux champs (objectif, compensation, scène, colorimétrie, auteur, copyright)
 *   - Page items/show : section dump brut (bouton "Toutes les métadonnées")
 *   - Page items/show : sans EXIF → section non affichée
 *   - Album show : nouveaux champs dans le JSON JS (lens, exposure_bias, scene_type, artist, copyright, color_space)
 */
class MediaExifTest extends TestCase
{
    private User $user;

    private MediaAlbum $album;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'admin']);
        $this->album = MediaAlbum::factory()->public()->create([
            'created_by' => $this->user->id,
        ]);
        $this->actingAs($this->user);
    }

    // =========================================================================
    // Page items/show — champs EXIF existants
    // =========================================================================

    public function test_page_item_affiche_appareil_et_reglages(): void
    {
        $item = MediaItem::factory()->create([
            'album_id' => $this->album->id,
            'mime_type' => 'image/jpeg',
            'exif_data' => [
                'Make' => 'Canon',
                'Model' => 'EOS R5',
                'ExposureTime' => 0.004,
                'FNumber' => 2.8,
                'ISOSpeedRatings' => 400,
                'FocalLength' => 85,
            ],
        ]);

        $this->get(route('media.items.show', [$this->album, $item]))
            ->assertOk()
            ->assertSee('Canon')
            ->assertSee('EOS R5')
            ->assertSee('1/250s')
            ->assertSee('f/2.8')
            ->assertSee('ISO 400')
            ->assertSee('85mm');
    }

    public function test_page_item_affiche_gps(): void
    {
        $item = MediaItem::factory()->create([
            'album_id' => $this->album->id,
            'mime_type' => 'image/jpeg',
            'exif_data' => [
                'GPSLatitude' => [46, 13, 12.0],
                'GPSLatitudeRef' => 'N',
                'GPSLongitude' => [1, 15, 30.0],
                'GPSLongitudeRef' => 'W',
            ],
        ]);

        $this->get(route('media.items.show', [$this->album, $item]))
            ->assertOk()
            ->assertSee('GPS');
    }

    // =========================================================================
    // Page items/show — nouveaux champs Task 6
    // =========================================================================

    public function test_page_item_affiche_objectif(): void
    {
        $item = MediaItem::factory()->create([
            'album_id' => $this->album->id,
            'mime_type' => 'image/jpeg',
            'exif_data' => [
                'LensMake' => 'Canon',
                'LensModel' => 'EF 85mm f/1.8 USM',
            ],
        ]);

        $this->get(route('media.items.show', [$this->album, $item]))
            ->assertOk()
            ->assertSee('Objectif')
            ->assertSee('EF 85mm f/1.8 USM');
    }

    public function test_page_item_affiche_objectif_sans_marque(): void
    {
        $item = MediaItem::factory()->create([
            'album_id' => $this->album->id,
            'mime_type' => 'image/jpeg',
            'exif_data' => [
                'LensModel' => '24-70mm f/2.8',
            ],
        ]);

        $this->get(route('media.items.show', [$this->album, $item]))
            ->assertOk()
            ->assertSee('24-70mm f/2.8');
    }

    public function test_page_item_affiche_compensation_exposition_positive(): void
    {
        $item = MediaItem::factory()->create([
            'album_id' => $this->album->id,
            'mime_type' => 'image/jpeg',
            'exif_data' => ['ExposureBiasValue' => 1.0],
        ]);

        $this->get(route('media.items.show', [$this->album, $item]))
            ->assertOk()
            ->assertSee('Compensation')
            ->assertSee('+1.0 EV');
    }

    public function test_page_item_affiche_compensation_exposition_negative(): void
    {
        $item = MediaItem::factory()->create([
            'album_id' => $this->album->id,
            'mime_type' => 'image/jpeg',
            'exif_data' => ['ExposureBiasValue' => -0.7],
        ]);

        $this->get(route('media.items.show', [$this->album, $item]))
            ->assertOk()
            ->assertSee('-0.7 EV');
    }

    public function test_page_item_masque_compensation_a_zero(): void
    {
        $item = MediaItem::factory()->create([
            'album_id' => $this->album->id,
            'mime_type' => 'image/jpeg',
            'exif_data' => ['ExposureBiasValue' => 0],
        ]);

        $response = $this->get(route('media.items.show', [$this->album, $item]))->assertOk();
        // "Compensation" ne doit pas apparaître quand la valeur est 0
        $this->assertStringNotContainsString('0.0 EV', $response->getContent());
    }

    public function test_page_item_affiche_type_scene(): void
    {
        $item = MediaItem::factory()->create([
            'album_id' => $this->album->id,
            'mime_type' => 'image/jpeg',
            'exif_data' => ['SceneCaptureType' => 1],
        ]);

        $this->get(route('media.items.show', [$this->album, $item]))
            ->assertOk()
            ->assertSee('Portrait');
    }

    public function test_page_item_affiche_colorimetrie_srgb(): void
    {
        $item = MediaItem::factory()->create([
            'album_id' => $this->album->id,
            'mime_type' => 'image/jpeg',
            'exif_data' => ['ColorSpace' => 1],
        ]);

        $this->get(route('media.items.show', [$this->album, $item]))
            ->assertOk()
            ->assertSee('sRGB');
    }

    public function test_page_item_affiche_auteur_et_copyright(): void
    {
        $item = MediaItem::factory()->create([
            'album_id' => $this->album->id,
            'mime_type' => 'image/jpeg',
            'exif_data' => [
                'Artist' => 'Jean-Pierre Bossé',
                'Copyright' => '© 2024 JPB',
            ],
        ]);

        $this->get(route('media.items.show', [$this->album, $item]))
            ->assertOk()
            ->assertSee('Jean-Pierre Bossé')
            ->assertSee('© 2024 JPB');
    }

    // =========================================================================
    // Dump brut EXIF
    // =========================================================================

    public function test_page_item_contient_bouton_toutes_metadonnees(): void
    {
        $item = MediaItem::factory()->create([
            'album_id' => $this->album->id,
            'mime_type' => 'image/jpeg',
            'exif_data' => [
                'Make' => 'Nikon',
                'Model' => 'Z6',
                'Copyright' => 'Test',
            ],
        ]);

        $this->get(route('media.items.show', [$this->album, $item]))
            ->assertOk()
            ->assertSee('Toutes les métadonnées')
            ->assertSee('Make')
            ->assertSee('Nikon');
    }

    public function test_page_item_sans_exif_masque_dump(): void
    {
        $item = MediaItem::factory()->create([
            'album_id' => $this->album->id,
            'mime_type' => 'image/jpeg',
            'exif_data' => null,
        ]);

        $this->get(route('media.items.show', [$this->album, $item]))
            ->assertOk()
            ->assertDontSee('Toutes les métadonnées');
    }

    // =========================================================================
    // Album show — champs dans le JSON JS (pour le panneau latéral)
    // =========================================================================

    public function test_album_show_inclut_lens_dans_json(): void
    {
        MediaItem::factory()->create([
            'album_id' => $this->album->id,
            'mime_type' => 'image/jpeg',
            'exif_data' => [
                'LensModel' => 'EF 85mm 1.8 USM',
            ],
        ]);

        $response = $this->get(route('media.albums.show', $this->album))->assertOk();

        $this->assertStringContainsString('EF 85mm 1.8 USM', $response->getContent());
    }

    public function test_album_show_inclut_artist_dans_json(): void
    {
        MediaItem::factory()->create([
            'album_id' => $this->album->id,
            'mime_type' => 'image/jpeg',
            'exif_data' => [
                'Artist' => 'Photographe Test',
            ],
        ]);

        $response = $this->get(route('media.albums.show', $this->album))->assertOk();

        $this->assertStringContainsString('Photographe Test', $response->getContent());
    }

    public function test_album_show_inclut_color_space_dans_json(): void
    {
        MediaItem::factory()->create([
            'album_id' => $this->album->id,
            'mime_type' => 'image/jpeg',
            'exif_data' => [
                'ColorSpace' => 2,
            ],
        ]);

        $response = $this->get(route('media.albums.show', $this->album))->assertOk();

        $this->assertStringContainsString('Adobe RGB', $response->getContent());
    }
}
