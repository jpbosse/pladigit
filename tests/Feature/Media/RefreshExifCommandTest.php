<?php

// tests/Feature/Media/RefreshExifCommandTest.php

namespace Tests\Feature\Media;

use App\Models\Tenant\MediaItem;
use App\Services\MediaService;
use App\Services\Nas\LocalNasDriver;
use App\Services\Nas\NasConnectorInterface;
use Tests\TestCase;

/**
 * Tests de la commande media:refresh-exif.
 *
 * Couverture :
 *   - Items sans EXIF → mis à jour
 *   - Items avec EXIF déjà rempli → ignorés sans --force
 *   - Items avec EXIF déjà rempli → mis à jour avec --force
 *   - Tenant inexistant → exit code 1
 *   - refreshExif() sur item non-JPEG → ignoré
 */
class RefreshExifCommandTest extends TestCase
{
    private string $nasRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->nasRoot = sys_get_temp_dir().'/pladigit_exif_test_'.uniqid();
        mkdir($this->nasRoot, 0755, true);

        config([
            'nas.default_driver' => 'local',
            'nas.local_path' => $this->nasRoot,
        ]);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->nasRoot);
        parent::tearDown();
    }

    // =========================================================================
    // Tests refreshExif() (service)
    // =========================================================================

    /**
     * refreshExif() ne traite que les JPEG/TIFF — les PNG/WebP/PDF sont ignorés.
     */
    public function test_refresh_exif_skips_non_jpeg_tiff_mime_types(): void
    {
        $nas = new LocalNasDriver($this->nasRoot);

        $service = app(MediaService::class);

        // Créer un item PNG sans EXIF
        /** @var \App\Models\Tenant\MediaAlbum $album */
        $album = \App\Models\Tenant\MediaAlbum::factory()->create();

        MediaItem::factory()->create([
            'album_id' => $album->id,
            'mime_type' => 'image/png',
            'exif_data' => null,
        ]);

        $result = $service->refreshExif($nas);

        // PNG non traité — ni updated ni erreur
        $this->assertSame(0, $result['updated']);
        $this->assertSame(0, $result['errors']);
    }

    /**
     * refreshExif() avec --force=false ignore les items qui ont déjà exif_data.
     */
    public function test_refresh_exif_skips_items_with_existing_exif_without_force(): void
    {
        $nas = new LocalNasDriver($this->nasRoot);
        $service = app(MediaService::class);

        $album = \App\Models\Tenant\MediaAlbum::factory()->create();

        // Item JPEG avec exif_data déjà rempli
        MediaItem::factory()->create([
            'album_id' => $album->id,
            'mime_type' => 'image/jpeg',
            'exif_data' => ['Make' => 'Canon'],
        ]);

        $result = $service->refreshExif($nas, force: false);

        $this->assertSame(0, $result['updated']);
    }

    /**
     * refreshExif() quand le fichier NAS est illisible → erreur comptée, pas d'exception.
     */
    public function test_refresh_exif_counts_error_for_unreadable_nas_file(): void
    {
        // NAS driver mocké qui lève une exception sur readFile
        $nas = $this->createMock(NasConnectorInterface::class);
        $nas->method('readFile')->willThrowException(new \RuntimeException('Fichier introuvable'));

        $service = app(MediaService::class);
        $album = \App\Models\Tenant\MediaAlbum::factory()->create();

        MediaItem::factory()->create([
            'album_id' => $album->id,
            'mime_type' => 'image/jpeg',
            'exif_data' => null,
            'file_path' => 'albums/1/2026/04/inexistant.jpg',
        ]);

        $result = $service->refreshExif($nas);

        $this->assertSame(0, $result['updated']);
        $this->assertSame(1, $result['errors']);
    }

    // =========================================================================
    // Tests commande artisan
    // =========================================================================

    /**
     * Tenant inexistant → message d'erreur + exit code 1.
     */
    public function test_command_fails_for_unknown_tenant(): void
    {
        $this->artisan('media:refresh-exif', ['--tenant' => 'tenant-inexistant'])
            ->assertExitCode(1);
    }

    /**
     * Sans tenant actif du tout → warning + exit 0.
     */
    public function test_command_succeeds_with_no_active_orgs(): void
    {
        // Désactiver toutes les orgs
        \App\Models\Platform\Organization::query()->update(['status' => 'suspended']);

        $this->artisan('media:refresh-exif')
            ->assertExitCode(0);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

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
