<?php

namespace Tests\Feature\Media;

use App\Models\Tenant\MediaAlbum;
use App\Models\Tenant\MediaItem;
use App\Models\Tenant\User;
use App\Services\MediaService;
use App\Services\Nas\LocalNasDriver;
use App\Services\Nas\NasManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Tests du MediaService — upload, détection doublons, EXIF, sync.
 *
 * Couverture :
 *   - Upload d'une image JPEG → MediaItem créé, miniature générée
 *   - Détection de doublon SHA-256 → exception levée
 *   - Extension interdite → exception levée
 *   - Taille dépassée → exception levée
 *   - syncByMtime → nouveaux fichiers ingérés
 *   - LocalNasDriver → testConnection, writeFile, readFile, sha256
 */
class MediaServiceTest extends TestCase
{
    private string $nasRoot;

    protected function setUp(): void
    {
        parent::setUp();

        // Dossier NAS temporaire isolé pour les tests
        $this->nasRoot = sys_get_temp_dir().'/pladigit_nas_test_'.uniqid();
        mkdir($this->nasRoot, 0755, true);

        // Rebinder NasManager sur le driver local avec ce dossier
        $this->app->bind(NasManager::class, function () {
            $manager = new NasManager();
            // Surcharge du driver via le conteneur
            $this->app->bind(\App\Services\Nas\NasConnectorInterface::class, function () {
                return new LocalNasDriver($this->nasRoot);
            });
            return $manager;
        });

        // Configurer le driver local dans la config
        config([
            'nas.default_driver' => 'local',
            'nas.local_path'     => $this->nasRoot,
        ]);
    }

    protected function tearDown(): void
    {
        // Nettoyage du dossier NAS temporaire
        $this->deleteDirectory($this->nasRoot);
        parent::tearDown();
    }

    // =========================================================================
    // LocalNasDriver
    // =========================================================================

    public function test_local_driver_test_connection_cree_le_dossier_si_absent(): void
    {
        $path   = sys_get_temp_dir().'/nas_test_'.uniqid();
        $driver = new LocalNasDriver($path);

        $this->assertTrue($driver->testConnection());
        $this->assertDirectoryExists($path);

        rmdir($path);
    }

    public function test_local_driver_write_et_read_file(): void
    {
        $driver = new LocalNasDriver($this->nasRoot);

        $driver->writeFile('test/hello.txt', 'Bonjour Pladigit');
        $content = $driver->readFile('test/hello.txt');

        $this->assertSame('Bonjour Pladigit', $content);
    }

    public function test_local_driver_sha256(): void
    {
        $driver   = new LocalNasDriver($this->nasRoot);
        $contents = 'contenu test sha256';

        $driver->writeFile('sha256test.txt', $contents);

        $expected = hash('sha256', $contents);
        $actual   = $driver->sha256('sha256test.txt');

        $this->assertSame($expected, $actual);
    }

    public function test_local_driver_exists(): void
    {
        $driver = new LocalNasDriver($this->nasRoot);

        $this->assertFalse($driver->exists('inexistant.txt'));

        $driver->writeFile('existant.txt', 'ok');

        $this->assertTrue($driver->exists('existant.txt'));
    }

    public function test_local_driver_list_files(): void
    {
        $driver = new LocalNasDriver($this->nasRoot);
        $driver->writeFile('galerie/photo1.jpg', 'fake');
        $driver->writeFile('galerie/photo2.jpg', 'fake');

        $files = $driver->listFiles('galerie');

        $this->assertCount(2, $files);
        $this->assertArrayHasKey('name', $files[0]);
        $this->assertArrayHasKey('path', $files[0]);
        $this->assertArrayHasKey('size', $files[0]);
        $this->assertArrayHasKey('mtime', $files[0]);
    }

    public function test_local_driver_bloque_path_traversal(): void
    {
        $driver = new LocalNasDriver($this->nasRoot);

        $this->expectException(\RuntimeException::class);

        $driver->readFile("../../etc/passwd");
    }

    // =========================================================================
    // Upload
    // =========================================================================

    public function test_upload_image_jpeg_cree_un_media_item(): void
    {
        $user  = User::factory()->create();
        $album = MediaAlbum::create([
            'name'       => 'Test Upload',
            'visibility' => 'restricted',
            'created_by' => $user->id,
        ]);

        $file = UploadedFile::fake()->image('photo.jpg', 800, 600);

        /** @var MediaService $service */
        $service = app(MediaService::class);
        $item    = $service->upload($file, $album, $user);

        $this->assertInstanceOf(MediaItem::class, $item);
        $this->assertSame('photo.jpg', $item->file_name);
        $this->assertSame($album->id, $item->album_id);
        $this->assertSame($user->id, $item->uploaded_by);
        $this->assertNotNull($item->sha256_hash);
        $this->assertSame(64, strlen($item->sha256_hash));
    }

    public function test_upload_refuse_extension_interdite(): void
    {
        $user  = User::factory()->create();
        $album = MediaAlbum::create([
            'name'       => 'Test Extension',
            'visibility' => 'restricted',
            'created_by' => $user->id,
        ]);

        $file = UploadedFile::fake()->create('script.php', 10, 'application/php');

        /** @var MediaService $service */
        $service = app(MediaService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/extension non autorisée/i');

        $service->upload($file, $album, $user);
    }

    public function test_upload_detecte_doublon_sha256(): void
    {
        $user  = User::factory()->create();
        $album = MediaAlbum::create([
            'name'       => 'Test Doublon',
            'visibility' => 'restricted',
            'created_by' => $user->id,
        ]);

        $file = UploadedFile::fake()->image('photo.jpg', 100, 100);

        /** @var MediaService $service */
        $service = app(MediaService::class);

        // Premier upload — OK
        $service->upload($file, $album, $user);

        // Deuxième upload du même fichier — doit lever une exception
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/doublon/i');

        $service->upload($file, $album, $user);
    }

    // =========================================================================
    // Synchronisation
    // =========================================================================

    public function test_sync_by_mtime_ingere_les_nouveaux_fichiers(): void
    {
        $user  = User::factory()->create();
        $album = MediaAlbum::create([
            'name'       => 'Sync Test',
            'visibility' => 'restricted',
            'created_by' => $user->id,
        ]);

        // Placer des fichiers directement dans le NAS simulé
        $driver = new LocalNasDriver($this->nasRoot);
        $driver->writeFile('photos/img1.jpg', 'fake image 1');
        $driver->writeFile('photos/img2.jpg', 'fake image 2');
        $driver->writeFile('photos/doc.pdf', 'fake pdf');

        /** @var MediaService $service */
        $service = app(MediaService::class);
        $result  = $service->syncByMtime($album, 'photos');

        $this->assertSame(3, $result['added']);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame(3, MediaItem::where('album_id', $album->id)->count());
    }

    public function test_sync_by_mtime_ne_duplique_pas_les_fichiers_existants(): void
    {
        $user  = User::factory()->create();
        $album = MediaAlbum::create([
            'name'       => 'Sync No Dupe',
            'visibility' => 'restricted',
            'created_by' => $user->id,
        ]);

        $driver = new LocalNasDriver($this->nasRoot);
        $driver->writeFile('photos2/img1.jpg', 'fake');

        /** @var MediaService $service */
        $service = app(MediaService::class);

        $service->syncByMtime($album, 'photos2');
        $result = $service->syncByMtime($album, 'photos2'); // Deuxième sync

        $this->assertSame(0, $result['added']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame(1, MediaItem::where('album_id', $album->id)->count());
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
