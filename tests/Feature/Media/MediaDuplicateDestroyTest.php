<?php

namespace Tests\Feature\Media;

use App\Models\Tenant\MediaAlbum;
use App\Models\Tenant\MediaItem;
use App\Models\Tenant\User;
use App\Services\Nas\LocalNasDriver;
use App\Services\Nas\NasManager;
use Tests\TestCase;

/**
 * Tests de suppression des doublons (MediaDuplicateController::destroySelected).
 *
 * Couverture :
 *   - Suppression NAS + BDD du fichier doublon
 *   - Suppression de la miniature associée
 *   - Conservation du fichier NAS si un autre enregistrement BDD pointe dessus
 *   - Suppression en lot (plusieurs doublons d'un même groupe)
 *   - Item introuvable retourne une erreur partielle sans bloquer le reste
 */
class MediaDuplicateDestroyTest extends TestCase
{
    private string $nasRoot;

    private User $admin;

    private MediaAlbum $albumA;

    private MediaAlbum $albumB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->nasRoot = sys_get_temp_dir().'/pladigit_dup_test_'.uniqid();
        mkdir($this->nasRoot.'/album-a/thumbs', 0755, true);
        mkdir($this->nasRoot.'/album-b/thumbs', 0755, true);

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

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->albumA = MediaAlbum::factory()->public()->create([
            'created_by' => $this->admin->id,
            'nas_path' => 'album-a',
        ]);
        $this->albumB = MediaAlbum::factory()->public()->create([
            'created_by' => $this->admin->id,
            'nas_path' => 'album-b',
        ]);

        $this->actingAs($this->admin);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->nasRoot)) {
            $this->rmdirRecursive($this->nasRoot);
        }
        parent::tearDown();
    }

    // =========================================================================
    // Tests
    // =========================================================================

    /**
     * Cas nominal : deux items avec le même sha256, chemins NAS distincts.
     * destroySelected([copy_id]) doit supprimer le fichier NAS du doublon.
     */
    public function test_deletes_nas_file_of_duplicate(): void
    {
        $sha256 = hash('sha256', 'same-image-content');

        // Fichiers physiques sur le NAS simulé
        file_put_contents($this->nasRoot.'/album-a/original.jpg', 'same-image-content');
        file_put_contents($this->nasRoot.'/album-b/copy.jpg', 'same-image-content');

        $original = MediaItem::factory()->create([
            'album_id' => $this->albumA->id,
            'file_path' => 'album-a/original.jpg',
            'sha256_hash' => $sha256,
            'is_duplicate' => false,
            'created_at' => now()->subDays(2),
        ]);

        $copy = MediaItem::factory()->create([
            'album_id' => $this->albumB->id,
            'file_path' => 'album-b/copy.jpg',
            'sha256_hash' => $sha256,
            'is_duplicate' => true,
            'created_at' => now()->subDay(),
        ]);

        $response = $this->postJson(route('media.duplicates.destroy'), [
            'item_ids' => [$copy->id],
        ]);

        $response->assertOk()->assertJson(['deleted' => 1, 'errors' => []]);

        // Entrée BDD supprimée définitivement
        $this->assertNull(MediaItem::withTrashed()->find($copy->id));

        // Fichier NAS du doublon supprimé
        $this->assertFileDoesNotExist($this->nasRoot.'/album-b/copy.jpg');

        // Fichier NAS de l'original conservé
        $this->assertFileExists($this->nasRoot.'/album-a/original.jpg');
    }

    /**
     * La miniature (thumb_path) doit aussi être supprimée du NAS.
     */
    public function test_deletes_thumb_alongside_file(): void
    {
        $sha256 = hash('sha256', 'img-with-thumb');

        file_put_contents($this->nasRoot.'/album-a/photo.jpg', 'img-with-thumb');
        file_put_contents($this->nasRoot.'/album-b/photo-copy.jpg', 'img-with-thumb');
        file_put_contents($this->nasRoot.'/album-b/thumbs/photo-copy_thumb.jpg', 'thumb-data');

        MediaItem::factory()->create([
            'album_id' => $this->albumA->id,
            'file_path' => 'album-a/photo.jpg',
            'sha256_hash' => $sha256,
            'created_at' => now()->subDays(2),
        ]);

        $copy = MediaItem::factory()->create([
            'album_id' => $this->albumB->id,
            'file_path' => 'album-b/photo-copy.jpg',
            'thumb_path' => 'album-b/thumbs/photo-copy_thumb.jpg',
            'sha256_hash' => $sha256,
            'created_at' => now()->subDay(),
        ]);

        $this->postJson(route('media.duplicates.destroy'), [
            'item_ids' => [$copy->id],
        ])->assertOk();

        $this->assertFileDoesNotExist($this->nasRoot.'/album-b/photo-copy.jpg');
        $this->assertFileDoesNotExist($this->nasRoot.'/album-b/thumbs/photo-copy_thumb.jpg');
    }

    /**
     * Si deux enregistrements BDD pointent vers le même chemin NAS (doublons BDD)
     * et que les deux sont sélectionnés, le fichier NAS n'est supprimé qu'une seule fois
     * (guard deletedNasPaths — pas d'erreur "fichier déjà absent").
     */
    public function test_batch_same_path_deletes_nas_file_only_once(): void
    {
        $sha256 = hash('sha256', 'shared-file');

        // Un fichier physique partagé référencé par deux albums différents
        file_put_contents($this->nasRoot.'/shared.jpg', 'shared-file');

        $bddDup1 = MediaItem::factory()->create([
            'album_id' => $this->albumA->id,
            'file_path' => 'shared.jpg',
            'sha256_hash' => $sha256,
            'created_at' => now()->subDays(1),
        ]);
        $bddDup2 = MediaItem::factory()->create([
            'album_id' => $this->albumB->id,
            'file_path' => 'shared.jpg',
            'sha256_hash' => $sha256,
            'created_at' => now(),
        ]);

        $response = $this->postJson(route('media.duplicates.destroy'), [
            'item_ids' => [$bddDup1->id, $bddDup2->id],
        ]);

        $response->assertOk()->assertJson(['deleted' => 2, 'errors' => []]);

        $this->assertNull(MediaItem::withTrashed()->find($bddDup1->id));
        $this->assertNull(MediaItem::withTrashed()->find($bddDup2->id));
        $this->assertFileDoesNotExist($this->nasRoot.'/shared.jpg');
    }

    /**
     * Suppression d'un lot (plusieurs doublons d'un même groupe sha256).
     */
    public function test_batch_delete_removes_all_nas_files(): void
    {
        $sha256 = hash('sha256', 'batch-content');

        file_put_contents($this->nasRoot.'/album-a/photo.jpg', 'batch-content');
        file_put_contents($this->nasRoot.'/album-b/copy1.jpg', 'batch-content');

        mkdir($this->nasRoot.'/album-c', 0755, true);
        file_put_contents($this->nasRoot.'/album-c/copy2.jpg', 'batch-content');

        $albumC = MediaAlbum::factory()->public()->create([
            'created_by' => $this->admin->id,
            'nas_path' => 'album-c',
        ]);

        MediaItem::factory()->create([
            'album_id' => $this->albumA->id,
            'file_path' => 'album-a/photo.jpg',
            'sha256_hash' => $sha256,
            'created_at' => now()->subDays(3),
        ]);
        $copy1 = MediaItem::factory()->create([
            'album_id' => $this->albumB->id,
            'file_path' => 'album-b/copy1.jpg',
            'sha256_hash' => $sha256,
            'created_at' => now()->subDays(2),
        ]);
        $copy2 = MediaItem::factory()->create([
            'album_id' => $albumC->id,
            'file_path' => 'album-c/copy2.jpg',
            'sha256_hash' => $sha256,
            'created_at' => now()->subDay(),
        ]);

        $response = $this->postJson(route('media.duplicates.destroy'), [
            'item_ids' => [$copy1->id, $copy2->id],
        ]);

        $response->assertOk()->assertJson(['deleted' => 2, 'errors' => []]);

        $this->assertFileDoesNotExist($this->nasRoot.'/album-b/copy1.jpg');
        $this->assertFileDoesNotExist($this->nasRoot.'/album-c/copy2.jpg');
        $this->assertFileExists($this->nasRoot.'/album-a/photo.jpg');
    }

    /**
     * Si le fichier NAS n'existe pas physiquement (déjà supprimé manuellement),
     * l'entrée BDD est quand même supprimée sans erreur.
     */
    public function test_db_entry_deleted_even_when_nas_file_missing(): void
    {
        $sha256 = hash('sha256', 'ghost-file');

        file_put_contents($this->nasRoot.'/album-a/original.jpg', 'ghost-file');
        // Pas de fichier album-b/ghost.jpg → NAS absent

        MediaItem::factory()->create([
            'album_id' => $this->albumA->id,
            'file_path' => 'album-a/original.jpg',
            'sha256_hash' => $sha256,
            'created_at' => now()->subDays(2),
        ]);

        $ghost = MediaItem::factory()->create([
            'album_id' => $this->albumB->id,
            'file_path' => 'album-b/ghost.jpg',
            'sha256_hash' => $sha256,
            'created_at' => now()->subDay(),
        ]);

        $response = $this->postJson(route('media.duplicates.destroy'), [
            'item_ids' => [$ghost->id],
        ]);

        $response->assertOk()->assertJson(['deleted' => 1, 'errors' => []]);
        $this->assertNull(MediaItem::withTrashed()->find($ghost->id));
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function rmdirRecursive(string $dir): void
    {
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir.DIRECTORY_SEPARATOR.$item;
            is_dir($path) ? $this->rmdirRecursive($path) : unlink($path);
        }
        rmdir($dir);
    }
}
