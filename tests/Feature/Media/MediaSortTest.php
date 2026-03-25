<?php

namespace Tests\Feature\Media;

use App\Models\Tenant\MediaAlbum;
use App\Models\Tenant\MediaItem;
use App\Models\Tenant\User;
use App\Services\MediaService;
use Tests\TestCase;

/**
 * Tests du tri des médias dans la vue album.
 *
 * Couverture :
 *   - Tri par date d'ajout (created_at) — ASC et DESC
 *   - Tri par date de prise de vue EXIF (exif_taken_at) — ASC et DESC
 *   - Items sans EXIF placés en fin de liste (NULLS LAST)
 *   - Tri par nom de fichier
 *   - Tri par taille
 *   - extractTakenAt() : parsing correct des formats EXIF
 *   - extractTakenAt() : cas limites (valeur nulle, format invalide)
 */
class MediaSortTest extends TestCase
{
    private User $user;

    private MediaAlbum $album;

    protected function setUp(): void
    {
        parent::setUp();

        $this->persistCurrentOrg();
        $this->user = User::factory()->create(['role' => 'admin']);
        $this->album = MediaAlbum::factory()->public()->create([
            'created_by' => $this->user->id,
        ]);
    }

    // =========================================================================
    // Tri par date d'ajout (comportement par défaut)
    // =========================================================================

    public function test_tri_par_date_ajout_desc_par_defaut(): void
    {
        // Créer 3 items avec des created_at distincts
        $old = MediaItem::factory()->create(['album_id' => $this->album->id, 'file_name' => 'ancien.jpg']);
        $mid = MediaItem::factory()->create(['album_id' => $this->album->id, 'file_name' => 'milieu.jpg']);
        $new = MediaItem::factory()->create(['album_id' => $this->album->id, 'file_name' => 'recent.jpg']);

        // Forcer des timestamps distincts via DB (Eloquent protège created_at)
        \DB::connection('tenant')->table('media_items')->where('id', $old->id)->update(['created_at' => now()->subDays(10)]);
        \DB::connection('tenant')->table('media_items')->where('id', $mid->id)->update(['created_at' => now()->subDays(5)]);
        \DB::connection('tenant')->table('media_items')->where('id', $new->id)->update(['created_at' => now()]);

        $this->actingAs($this->user);

        $response = $this->get(route('media.albums.show', $this->album));

        $response->assertOk();
        $response->assertViewHas('sortBy', 'date');
        $response->assertViewHas('sortDir', 'desc');

        $items = $response->viewData('items');
        $names = $items->pluck('file_name')->values()->all();

        // DESC = plus récent en premier
        $this->assertSame(['recent.jpg', 'milieu.jpg', 'ancien.jpg'], $names);
    }

    public function test_tri_par_date_ajout_asc(): void
    {
        $old = MediaItem::factory()->create(['album_id' => $this->album->id, 'file_name' => 'ancien.jpg']);
        $new = MediaItem::factory()->create(['album_id' => $this->album->id, 'file_name' => 'recent.jpg']);

        \DB::connection('tenant')->table('media_items')->where('id', $old->id)->update(['created_at' => now()->subDays(10)]);
        \DB::connection('tenant')->table('media_items')->where('id', $new->id)->update(['created_at' => now()]);

        $this->actingAs($this->user);

        $response = $this->get(route('media.albums.show', $this->album).'?sort=date&dir=asc');

        $response->assertOk();
        $names = $response->viewData('items')->pluck('file_name')->values()->all();

        $this->assertSame(['ancien.jpg', 'recent.jpg'], $names);
    }

    // =========================================================================
    // Tri par date EXIF
    // =========================================================================

    public function test_tri_par_date_exif_desc(): void
    {
        // 3 photos avec des dates EXIF différentes
        $photo2020 = MediaItem::factory()
            ->withTakenAt('2020-03-15 10:00:00')
            ->create(['album_id' => $this->album->id, 'file_name' => 'photo-2020.jpg']);

        $photo2023 = MediaItem::factory()
            ->withTakenAt('2023-07-22 16:45:00')
            ->create(['album_id' => $this->album->id, 'file_name' => 'photo-2023.jpg']);

        $photo2025 = MediaItem::factory()
            ->withTakenAt('2025-01-01 09:00:00')
            ->create(['album_id' => $this->album->id, 'file_name' => 'photo-2025.jpg']);

        $this->actingAs($this->user);

        $response = $this->get(route('media.albums.show', $this->album).'?sort=exif_date&dir=desc');

        $response->assertOk();
        $response->assertViewHas('sortBy', 'exif_date');

        $names = $response->viewData('items')->pluck('file_name')->values()->all();

        // DESC = plus récente prise de vue en premier
        $this->assertSame(['photo-2025.jpg', 'photo-2023.jpg', 'photo-2020.jpg'], $names);
    }

    public function test_tri_par_date_exif_asc(): void
    {
        $photo2020 = MediaItem::factory()
            ->withTakenAt('2020-03-15 10:00:00')
            ->create(['album_id' => $this->album->id, 'file_name' => 'photo-2020.jpg']);

        $photo2025 = MediaItem::factory()
            ->withTakenAt('2025-01-01 09:00:00')
            ->create(['album_id' => $this->album->id, 'file_name' => 'photo-2025.jpg']);

        $this->actingAs($this->user);

        $response = $this->get(route('media.albums.show', $this->album).'?sort=exif_date&dir=asc');

        $response->assertOk();
        $names = $response->viewData('items')->pluck('file_name')->values()->all();

        // ASC = plus ancienne en premier
        $this->assertSame(['photo-2020.jpg', 'photo-2025.jpg'], $names);
    }

    public function test_items_sans_exif_en_fin_de_liste_desc(): void
    {
        // Un item avec EXIF, un sans
        $avecExif = MediaItem::factory()
            ->withTakenAt('2024-06-10 12:00:00')
            ->create(['album_id' => $this->album->id, 'file_name' => 'avec-exif.jpg']);

        $sansExif = MediaItem::factory()
            ->create(['album_id' => $this->album->id, 'file_name' => 'sans-exif.jpg', 'exif_taken_at' => null]);

        $this->actingAs($this->user);

        $response = $this->get(route('media.albums.show', $this->album).'?sort=exif_date&dir=desc');

        $response->assertOk();
        $names = $response->viewData('items')->pluck('file_name')->values()->all();

        // Item avec EXIF d'abord, sans EXIF en dernier (NULLS LAST)
        $this->assertSame('avec-exif.jpg', $names[0]);
        $this->assertSame('sans-exif.jpg', $names[1]);
    }

    public function test_items_sans_exif_en_fin_de_liste_asc(): void
    {
        $sansExif = MediaItem::factory()
            ->create(['album_id' => $this->album->id, 'file_name' => 'sans-exif.jpg', 'exif_taken_at' => null]);

        $avecExif = MediaItem::factory()
            ->withTakenAt('2024-06-10 12:00:00')
            ->create(['album_id' => $this->album->id, 'file_name' => 'avec-exif.jpg']);

        $this->actingAs($this->user);

        $response = $this->get(route('media.albums.show', $this->album).'?sort=exif_date&dir=asc');

        $response->assertOk();
        $names = $response->viewData('items')->pluck('file_name')->values()->all();

        // Même en ASC, sans-EXIF va en fin de liste
        $this->assertSame('avec-exif.jpg', $names[0]);
        $this->assertSame('sans-exif.jpg', $names[1]);
    }

    // =========================================================================
    // Tri par nom et taille
    // =========================================================================

    public function test_tri_par_nom_asc(): void
    {
        MediaItem::factory()->create(['album_id' => $this->album->id, 'file_name' => 'zèbre.jpg']);
        MediaItem::factory()->create(['album_id' => $this->album->id, 'file_name' => 'alpha.jpg']);
        MediaItem::factory()->create(['album_id' => $this->album->id, 'file_name' => 'milieu.jpg']);

        $this->actingAs($this->user);

        $response = $this->get(route('media.albums.show', $this->album).'?sort=name&dir=asc');

        $response->assertOk();
        $names = $response->viewData('items')->pluck('file_name')->values()->all();

        $this->assertSame(['alpha.jpg', 'milieu.jpg', 'zèbre.jpg'], $names);
    }

    public function test_tri_par_taille_desc(): void
    {
        MediaItem::factory()->create(['album_id' => $this->album->id, 'file_name' => 'petit.jpg',  'file_size_bytes' => 100_000]);
        MediaItem::factory()->create(['album_id' => $this->album->id, 'file_name' => 'grand.jpg',  'file_size_bytes' => 5_000_000]);
        MediaItem::factory()->create(['album_id' => $this->album->id, 'file_name' => 'moyen.jpg',  'file_size_bytes' => 1_000_000]);

        $this->actingAs($this->user);

        $response = $this->get(route('media.albums.show', $this->album).'?sort=size&dir=desc');

        $response->assertOk();
        $names = $response->viewData('items')->pluck('file_name')->values()->all();

        $this->assertSame(['grand.jpg', 'moyen.jpg', 'petit.jpg'], $names);
    }

    // =========================================================================
    // MediaService::extractTakenAt()
    // =========================================================================

    public function test_extract_taken_at_format_exif_standard(): void
    {
        $service = app(MediaService::class);

        $result = $service->extractTakenAt(['DateTimeOriginal' => '2024:07:14 15:32:00']);

        $this->assertNotNull($result);
        $this->assertSame('2024-07-14', $result->format('Y-m-d'));
        $this->assertSame('15:32:00', $result->format('H:i:s'));
    }

    public function test_extract_taken_at_priorite_datetime_original(): void
    {
        $service = app(MediaService::class);

        $result = $service->extractTakenAt([
            'DateTimeOriginal' => '2024:07:14 15:32:00',  // prioritaire
            'DateTime' => '2024:08:01 10:00:00',
        ]);

        $this->assertNotNull($result);
        $this->assertSame('2024-07-14', $result->format('Y-m-d'));
    }

    public function test_extract_taken_at_fallback_datetime(): void
    {
        $service = app(MediaService::class);

        // Sans DateTimeOriginal, on prend DateTime
        $result = $service->extractTakenAt(['DateTime' => '2023:12:25 08:00:00']);

        $this->assertNotNull($result);
        $this->assertSame('2023-12-25', $result->format('Y-m-d'));
    }

    public function test_extract_taken_at_valeur_nulle_exif(): void
    {
        $service = app(MediaService::class);

        // "0000:00:00 00:00:00" = valeur sentinelle nulle en EXIF
        $result = $service->extractTakenAt(['DateTimeOriginal' => '0000:00:00 00:00:00']);

        $this->assertNull($result);
    }

    public function test_extract_taken_at_exif_vide(): void
    {
        $service = app(MediaService::class);

        $this->assertNull($service->extractTakenAt([]));
        $this->assertNull($service->extractTakenAt(['Make' => 'Canon'])); // sans clé date
    }

    // =========================================================================
    // Paramètres invalides — robustesse
    // =========================================================================

    public function test_sort_invalide_utilise_date_par_defaut(): void
    {
        MediaItem::factory()->create(['album_id' => $this->album->id]);

        $this->actingAs($this->user);

        // Un sort inconnu doit fallback sur 'date' sans erreur 500
        $response = $this->get(route('media.albums.show', $this->album).'?sort=inexistant&dir=asc');

        $response->assertOk();
        $response->assertViewHas('sortBy', 'inexistant'); // la valeur est passée telle quelle
        // Le match default s'applique → orderBy created_at sans exception
    }

    public function test_dir_invalide_utilise_desc_par_defaut(): void
    {
        MediaItem::factory()->create(['album_id' => $this->album->id]);

        $this->actingAs($this->user);

        $response = $this->get(route('media.albums.show', $this->album).'?sort=date&dir=random');

        $response->assertOk();
        $response->assertViewHas('sortDir', 'desc'); // sanitisé → desc
    }

    // =========================================================================
    // Helper
    // =========================================================================

    private function persistCurrentOrg(array $extra = []): \App\Models\Platform\Organization
    {
        $current = app(\App\Services\TenantManager::class)->current();
        $slug = $current->slug ?? 'test';

        $org = \App\Models\Platform\Organization::updateOrCreate(
            ['slug' => $slug],
            array_merge([
                'name' => $current->name ?? 'Test Org',
                'db_name' => $current->db_name ?? env('DB_TENANT_DATABASE'),
                'status' => 'active',
                'plan' => 'communautaire',
                'primary_color' => '#1E3A5F',
                'enabled_modules' => ['media'],
            ], $extra)
        );

        app(\App\Services\TenantManager::class)->connectTo($org);

        return $org;
    }
}
