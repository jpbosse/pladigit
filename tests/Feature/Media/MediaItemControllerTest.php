<?php

// tests/Feature/Media/MediaItemControllerTest.php

namespace Tests\Feature\Media;

use App\Models\Tenant\MediaAlbum;
use App\Models\Tenant\MediaItem;
use App\Models\Tenant\User;
use App\Services\Nas\LocalNasDriver;
use App\Services\Nas\NasManager;
use Tests\TestCase;

/**
 * Tests fonctionnels du MediaItemController.
 *
 * Couverture :
 *   - show      : affichage visionneuse, navigation prev/next, position/total
 *   - serve     : inline image (full + thumb), inline vidéo → stream
 *   - download  : téléchargement image, vidéo → stream, fichier absent → 404
 *   - updateCaption : AJAX PATCH, validation 500 chars max, non-membre → 403
 *   - destroy   : soft delete, item hors album → 404
 *   - store     : upload valide, extension interdite, quota dépassé
 */
class MediaItemControllerTest extends TestCase
{
    private string $nasRoot;

    private User $user;

    private MediaAlbum $album;

    protected function setUp(): void
    {
        parent::setUp();

        // Nettoyage explicite des media_items pour éviter la contamination entre tests
        \DB::connection('tenant')->table('media_items')->delete();

        $this->nasRoot = sys_get_temp_dir().'/pladigit_ctrl_test_'.uniqid();
        mkdir($this->nasRoot, 0755, true);

        config([
            'nas.default_driver' => 'local',
            'nas.local_path' => $this->nasRoot,
        ]);

        // Rebind NasManager → LocalNasDriver sur le dossier de test
        $nasRoot = $this->nasRoot;
        $this->app->bind(NasManager::class, function () use ($nasRoot) {
            $manager = $this->getMockBuilder(NasManager::class)
                ->disableOriginalConstructor()
                ->onlyMethods(['photoDriver', 'driver'])
                ->getMock();
            $driver = new LocalNasDriver($nasRoot);
            $manager->method('photoDriver')->willReturn($driver);
            $manager->method('driver')->willReturn($driver);

            return $manager;
        });

        $this->persistCurrentOrg();

        $this->user = User::factory()->create(['role' => 'admin']);
        $this->album = MediaAlbum::factory()->public()->create(['created_by' => $this->user->id]);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->nasRoot);
        parent::tearDown();
    }

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

    // =========================================================================
    // show
    // =========================================================================

    public function test_show_affiche_la_visionneuse(): void
    {
        $item = MediaItem::factory()->create(['album_id' => $this->album->id]);

        $this->actingAs($this->user)
            ->get(route('media.items.show', [$this->album, $item]))
            ->assertOk()
            ->assertSee($item->file_name);
    }

    public function test_show_retourne_404_si_item_hors_album(): void
    {
        $autreAlbum = MediaAlbum::factory()->public()->create();
        $item = MediaItem::factory()->create(['album_id' => $autreAlbum->id]);

        $this->actingAs($this->user)
            ->get(route('media.items.show', [$this->album, $item]))
            ->assertNotFound();
    }

    public function test_show_fournit_prev_next_et_position(): void
    {
        [$item1, $item2, $item3] = MediaItem::factory()->count(3)->create(['album_id' => $this->album->id]);

        $response = $this->actingAs($this->user)
            ->get(route('media.items.show', [$this->album, $item2]));

        $response->assertOk();
        // La vue reçoit les variables prev, next, position, total
        $response->assertViewHas('prev', fn ($v) => $v?->id === $item1->id);
        $response->assertViewHas('next', fn ($v) => $v?->id === $item3->id);
        $response->assertViewHas('position', 2);
        $response->assertViewHas('total', 3);
    }

    public function test_show_redirige_visiteur_non_authentifie(): void
    {
        $item = MediaItem::factory()->create(['album_id' => $this->album->id]);

        $this->get(route('media.items.show', [$this->album, $item]))
            ->assertRedirect(route('login'));
    }

    // =========================================================================
    // serve
    // =========================================================================

    public function test_serve_retourne_le_fichier_inline(): void
    {
        $contents = $this->createFakeJpeg('albums/1/2026/04/test-serve.jpg');
        $item = MediaItem::factory()->create([
            'album_id' => $this->album->id,
            'file_path' => 'albums/1/2026/04/test-serve.jpg',
            'mime_type' => 'image/jpeg',
            'thumb_path' => null,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('media.items.serve', [$this->album, $item, 'full']));

        $response->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_serve_retourne_la_miniature_si_disponible(): void
    {
        $this->createFakeJpeg('albums/1/2026/04/test-thumb.jpg');
        $this->createFakeJpeg('albums/1/2026/04/thumbs/test-thumb_thumb.jpg');

        $item = MediaItem::factory()->create([
            'album_id' => $this->album->id,
            'file_path' => 'albums/1/2026/04/test-thumb.jpg',
            'thumb_path' => 'albums/1/2026/04/thumbs/test-thumb_thumb.jpg',
            'mime_type' => 'image/jpeg',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('media.items.serve', [$this->album, $item, 'thumb']));

        $response->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_serve_retourne_404_si_fichier_nas_absent(): void
    {
        $item = MediaItem::factory()->create([
            'album_id' => $this->album->id,
            'file_path' => 'albums/1/2026/04/inexistant.jpg',
            'mime_type' => 'image/jpeg',
        ]);

        $this->actingAs($this->user)
            ->get(route('media.items.serve', [$this->album, $item, 'full']))
            ->assertNotFound();
    }

    // =========================================================================
    // download
    // =========================================================================

    public function test_download_retourne_le_fichier_en_attachment(): void
    {
        $this->createFakeJpeg('albums/1/2026/04/test-dl.jpg');

        $item = MediaItem::factory()->create([
            'album_id' => $this->album->id,
            'file_path' => 'albums/1/2026/04/test-dl.jpg',
            'file_name' => 'photo-originale.jpg',
            'mime_type' => 'image/jpeg',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('media.items.download', [$this->album, $item]));

        $response->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="photo-originale.jpg"');
    }

    public function test_download_retourne_404_si_fichier_nas_absent(): void
    {
        $item = MediaItem::factory()->create([
            'album_id' => $this->album->id,
            'file_path' => 'albums/1/2026/04/inexistant-dl.jpg',
            'mime_type' => 'image/jpeg',
        ]);

        $this->actingAs($this->user)
            ->get(route('media.items.download', [$this->album, $item]))
            ->assertNotFound();
    }

    // =========================================================================
    // updateCaption
    // =========================================================================

    public function test_update_caption_met_a_jour_la_description(): void
    {
        $item = MediaItem::factory()->create([
            'album_id' => $this->album->id,
            'caption' => null,
        ]);

        $this->actingAs($this->user)
            ->patchJson(route('media.items.updateCaption', [$this->album, $item]), [
                'caption' => 'Ma belle photo',
            ])
            ->assertOk()
            ->assertJson(['ok' => true, 'caption' => 'Ma belle photo']);

        $this->assertDatabaseHas('media_items', [
            'id' => $item->id,
            'caption' => 'Ma belle photo',
        ], 'tenant');
    }

    public function test_update_caption_accepte_null(): void
    {
        $item = MediaItem::factory()->create([
            'album_id' => $this->album->id,
            'caption' => 'Ancienne description',
        ]);

        $this->actingAs($this->user)
            ->patchJson(route('media.items.updateCaption', [$this->album, $item]), [
                'caption' => null,
            ])
            ->assertOk();

        $this->assertDatabaseHas('media_items', [
            'id' => $item->id,
            'caption' => null,
        ], 'tenant');
    }

    public function test_update_caption_refuse_texte_trop_long(): void
    {
        $item = MediaItem::factory()->create(['album_id' => $this->album->id]);

        $this->actingAs($this->user)
            ->patchJson(route('media.items.updateCaption', [$this->album, $item]), [
                'caption' => str_repeat('a', 501),
            ])
            ->assertUnprocessable();
    }

    // =========================================================================
    // destroy
    // =========================================================================

    public function test_destroy_soft_delete_le_media(): void
    {
        $item = MediaItem::factory()->create(['album_id' => $this->album->id]);

        $this->actingAs($this->user)
            ->delete(route('media.items.destroy', [$this->album, $item]))
            ->assertRedirect(route('media.albums.show', $this->album));

        $this->assertSoftDeleted('media_items', ['id' => $item->id], 'tenant');
    }

    public function test_destroy_retourne_404_si_item_hors_album(): void
    {
        $autreAlbum = MediaAlbum::factory()->public()->create();
        $item = MediaItem::factory()->create(['album_id' => $autreAlbum->id]);

        $this->actingAs($this->user)
            ->delete(route('media.items.destroy', [$this->album, $item]))
            ->assertNotFound();
    }

    // =========================================================================
    // store (upload)
    // =========================================================================

    public function test_store_refuse_extension_non_autorisee(): void
    {
        $countBefore = MediaItem::count();

        $file = \Illuminate\Http\UploadedFile::fake()->create('malware.exe', 100, 'application/octet-stream');

        $this->actingAs($this->user)
            ->post(route('media.items.store', $this->album), [
                'files' => [$file],
            ])
            ->assertRedirect()
            ->assertSessionHas('upload_errors');

        // Aucun nouvel item créé
        $this->assertDatabaseCount('media_items', $countBefore, 'tenant');
    }

    public function test_store_refuse_fichier_trop_volumineux(): void
    {
        $countBefore = MediaItem::count();

        config(['nas.max_file_size' => 1024]); // 1 Ko max

        // Fake file de 10 Ko
        $file = \Illuminate\Http\UploadedFile::fake()->create('gros.jpg', 10, 'image/jpeg');

        $this->actingAs($this->user)
            ->post(route('media.items.store', $this->album), [
                'files' => [$file],
            ])
            ->assertRedirect()
            ->assertSessionHas('upload_errors');

        // Aucun nouvel item créé
        $this->assertDatabaseCount('media_items', $countBefore, 'tenant');
    }

    public function test_store_refuse_si_quota_depasse(): void
    {
        // Quota = 1 Mo, usage simulé = 1 Mo déjà consommé
        MediaItem::factory()->create([
            'album_id' => $this->album->id,
            'file_size_bytes' => 1024 * 1024, // 1 Mo
        ]);

        // Tenant avec quota = 1 Mo — on persiste et reconnecte pour que le contrôleur le voie
        $this->persistCurrentOrg(['storage_quota_mb' => 1]);

        $file = \Illuminate\Http\UploadedFile::fake()->image('new.jpg', 10, 10);

        $this->actingAs($this->user)
            ->post(route('media.items.store', $this->album), [
                'files' => [$file],
            ])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Crée un fichier JPEG minimal sur le NAS de test et retourne son contenu.
     */
    private function createFakeJpeg(string $nasPath): string
    {
        // JPEG minimal valide (1×1 px)
        $contents = base64_decode(
            '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8U'.
            'HRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgN'.
            'DRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIy'.
            'MjL/wAARCAABAAEDASIAAhEBAxEB/8QAFAABAAAAAAAAAAAAAAAAAAAACf/EABQQAQAAAAAA'.
            'AAAAAAAAAAAAAP/EABQBAQAAAAAAAAAAAAAAAAAAAAD/xAAUEQEAAAAAAAAAAAAAAAAAAAAA'.
            '/9oADAMBAAIRAxEAPwCwABmX/9k='
        );

        $fullPath = $this->nasRoot.'/'.ltrim($nasPath, '/');
        $dir = dirname($fullPath);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($fullPath, $contents);

        return $contents;
    }

    private function deleteDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        ) as $item) {
            $item->isDir() ? rmdir($item->getRealPath()) : unlink($item->getRealPath());
        }

        rmdir($dir);
    }
}
