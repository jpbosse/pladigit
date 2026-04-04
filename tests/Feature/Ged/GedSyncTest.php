<?php

namespace Tests\Feature\Ged;

use App\Enums\UserRole;
use App\Models\Tenant\GedDocument;
use App\Models\Tenant\GedFolder;
use App\Models\Tenant\User;
use App\Services\Ged\GedNasDriver;
use App\Services\Ged\GedStorageInterface;
use App\Services\Ged\GedSyncService;
use App\Services\Nas\LocalNasDriver;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Tests fonctionnels — GED, synchronisation NAS → BDD.
 *
 * Couverture :
 *   - Création de dossiers et documents depuis l'arborescence NAS
 *   - Fichiers avec MIME non autorisé → ignorés + error_details
 *   - Soft-delete des documents orphelins (fichier disparu du NAS)
 *   - Soft-delete des dossiers orphelins vides (répertoire disparu du NAS)
 *   - Verrou concurrent → abandon silencieux (skipped_reason = 'lock')
 *
 * Utilise un répertoire temporaire réel car LocalNasDriver s'appuie sur
 * les fonctions PHP natives (is_dir, file_put_contents…).
 */
class GedSyncTest extends TestCase
{
    private string $nasRoot;

    private GedSyncService $syncService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->nasRoot = sys_get_temp_dir().'/ged_sync_test_'.uniqid('', true);
        mkdir($this->nasRoot, 0755, true);

        $this->syncService = new GedSyncService;

        // Un admin est requis pour que GedSyncService puisse affecter un propriétaire.
        User::factory()->create(['role' => UserRole::ADMIN->value]);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->nasRoot);
        parent::tearDown();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makeNas(): GedStorageInterface
    {
        return new GedNasDriver(new LocalNasDriver($this->nasRoot));
    }

    private function nasFile(string $relPath, string $content = 'dummy'): void
    {
        $full = $this->nasRoot.'/'.$relPath;
        $dir = dirname($full);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($full, $content);
    }

    private function nasDir(string $relPath): void
    {
        mkdir($this->nasRoot.'/'.$relPath, 0755, true);
    }

    private function removeDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getRealPath()) : @unlink($item->getRealPath());
        }
        @rmdir($path);
    }

    // =========================================================================
    // Création de dossiers et documents
    // =========================================================================

    public function test_sync_cree_les_dossiers_et_documents(): void
    {
        // Arborescence NAS :
        //   rh/
        //     contrat.pdf
        //     bulletin.pdf
        //   compta/
        //     bilan.pdf
        $this->nasDir('rh');
        $this->nasFile('rh/contrat.pdf');
        $this->nasFile('rh/bulletin.pdf');
        $this->nasDir('compta');
        $this->nasFile('compta/bilan.pdf');

        $result = $this->syncService->syncFolderTree($this->makeNas());

        $this->assertSame(2, $result['folders_created']);
        $this->assertSame(3, $result['files_added']);
        $this->assertSame(0, $result['errors']);

        $this->assertDatabaseHas('ged_folders', ['name' => 'rh',    'nas_path' => 'rh'], 'tenant');
        $this->assertDatabaseHas('ged_folders', ['name' => 'compta', 'nas_path' => 'compta'], 'tenant');
        $this->assertDatabaseHas('ged_documents', ['name' => 'contrat.pdf'], 'tenant');
        $this->assertDatabaseHas('ged_documents', ['name' => 'bulletin.pdf'], 'tenant');
        $this->assertDatabaseHas('ged_documents', ['name' => 'bilan.pdf'], 'tenant');
    }

    public function test_sync_idempotente_ne_recreee_pas_lexistant(): void
    {
        $this->nasDir('rh');
        $this->nasFile('rh/contrat.pdf');

        // Première sync
        $this->syncService->syncFolderTree($this->makeNas());

        // Deuxième sync
        $result = $this->syncService->syncFolderTree($this->makeNas());

        $this->assertSame(0, $result['folders_created'], 'Aucun dossier ne doit être recréé');
        $this->assertSame(1, $result['folders_found']);
        $this->assertSame(0, $result['files_added'], 'Aucun document ne doit être réingéré');
        $this->assertSame(1, $result['files_skipped']);

        $this->assertSame(1, GedFolder::count());
        $this->assertSame(1, GedDocument::count());
    }

    // =========================================================================
    // Validation MIME
    // =========================================================================

    public function test_sync_ignore_les_fichiers_mime_interdit(): void
    {
        config(['ged.allowed_mimes' => ['application/pdf']]);

        $this->nasDir('docs');
        $this->nasFile('docs/rapport.pdf');
        $this->nasFile('docs/virus.exe');

        $result = $this->syncService->syncFolderTree($this->makeNas());

        $this->assertSame(1, $result['files_added'], 'Seul le PDF est ingéré');
        $this->assertSame(1, $result['files_skipped'], 'L\'exe est ignoré');
        $this->assertSame(1, $result['errors']);
        $this->assertCount(1, $result['error_details']);
        $this->assertStringContainsString('.exe', $result['error_details'][0]['reason']);

        $this->assertDatabaseMissing('ged_documents', ['name' => 'virus.exe'], 'tenant');
    }

    // =========================================================================
    // Purge documents orphelins
    // =========================================================================

    public function test_sync_supprime_les_documents_orphelins(): void
    {
        // Dossier + fichier existants en base (nas_path non null)
        $folder = GedFolder::create([
            'name' => 'rh',
            'slug' => 'rh',
            'path' => '/rh',
            'nas_path' => 'rh',
            'parent_id' => null,
            'is_private' => false,
            'created_by' => User::first()->id,
        ]);

        $doc = GedDocument::create([
            'folder_id' => $folder->id,
            'name' => 'contrat.pdf',
            'disk_path' => 'rh/contrat.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'current_version' => 1,
            'created_by' => User::first()->id,
        ]);

        // Le fichier n'existe PAS sur le NAS simulé (nasRoot vide)
        $this->nasDir('rh');
        // contrat.pdf absent intentionnellement

        $result = $this->syncService->syncFolderTree($this->makeNas());

        $this->assertSame(1, $result['files_removed']);

        $this->assertTrue(GedDocument::withTrashed()->find($doc->id)?->trashed() ?? false);
    }

    // =========================================================================
    // Purge dossiers orphelins
    // =========================================================================

    public function test_sync_supprime_les_dossiers_orphelins_vides(): void
    {
        $folder = GedFolder::create([
            'name' => 'ancien',
            'slug' => 'ancien',
            'path' => '/ancien',
            'nas_path' => 'ancien',
            'parent_id' => null,
            'is_private' => false,
            'created_by' => User::first()->id,
        ]);

        // Le répertoire 'ancien' n'existe PAS dans le nasRoot simulé
        // (nasRoot vide — aucun dossier créé)

        $result = $this->syncService->syncFolderTree($this->makeNas());

        $this->assertSame(1, $result['folders_removed']);

        $this->assertTrue(GedFolder::withTrashed()->find($folder->id)?->trashed() ?? false);
    }

    // =========================================================================
    // Verrou concurrent
    // =========================================================================

    public function test_sync_abandonne_si_verrou_deja_pris(): void
    {
        $lockKey = 'ged_sync_lock_'.md5(config('database.connections.tenant.database', 'tenant'));
        $lock = Cache::lock($lockKey, 600);

        $acquired = $lock->get();
        $this->assertTrue($acquired, 'Le lock doit pouvoir être acquis pour le test');

        try {
            $result = $this->syncService->syncFolderTree($this->makeNas());

            $this->assertArrayHasKey('skipped_reason', $result);
            $this->assertSame('lock', $result['skipped_reason']);
            $this->assertSame(0, $result['folders_created']);
            $this->assertSame(0, $result['files_added']);
        } finally {
            $lock->release();
        }
    }
}
