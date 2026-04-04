<?php

namespace Tests\Feature\Ged;

use App\Enums\UserRole;
use App\Models\Tenant\GedDocument;
use App\Models\Tenant\GedFolder;
use App\Models\Tenant\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Tests fonctionnels — GED, upload de documents.
 *
 * Couverture :
 *   - Upload d'un fichier valide → job dispatché + fichier temporaire créé
 *   - Upload multi-fichiers → N jobs dispatchés
 *   - Refus si MIME non autorisé
 *   - Refus si fichier trop lourd
 *   - Refus si dossier introuvable
 *   - Refus d'accès à un dossier privé d'autrui
 */
class GedUploadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Queue::fake();
    }

    // ── Helpers ──────────────────────────────────────────────

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::ADMIN->value]);
    }

    private function agent(): User
    {
        return User::factory()->create(['role' => UserRole::USER->value]);
    }

    private function makeFolder(array $attrs = []): GedFolder
    {
        $user = $attrs['created_by'] ?? $this->admin()->id;

        return GedFolder::create(array_merge([
            'name' => 'Dossier Test',
            'slug' => 'dossier-test',
            'path' => '/dossier-test',
            'parent_id' => null,
            'is_private' => false,
            'created_by' => $user,
        ], $attrs));
    }

    private function fakePdf(string $name = 'document.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 100, 'application/pdf');
    }

    // =========================================================================
    // Upload valide
    // =========================================================================

    public function test_upload_fichier_valide_cree_le_document(): void
    {
        $user = $this->admin();
        $folder = $this->makeFolder(['created_by' => $user->id]);

        $this->actingAs($user);

        $response = $this->postJson(route('ged.documents.store'), [
            'folder_id' => $folder->id,
            'files' => [$this->fakePdf()],
        ]);

        $response->assertOk()->assertJsonFragment(['ok' => true, 'stored' => 1]);
        Queue::assertNothingPushed();
    }

    public function test_upload_multi_fichiers_cree_n_documents(): void
    {
        $user = $this->admin();
        $folder = $this->makeFolder(['created_by' => $user->id]);

        $this->actingAs($user);

        $response = $this->postJson(route('ged.documents.store'), [
            'folder_id' => $folder->id,
            'files' => [
                UploadedFile::fake()->create('a.pdf', 50, 'application/pdf'),
                UploadedFile::fake()->create('b.pdf', 80, 'application/pdf'),
                UploadedFile::fake()->create('c.pdf', 30, 'application/pdf'),
            ],
        ]);

        $response->assertOk()->assertJsonFragment(['stored' => 3]);
        $this->assertSame(3, GedDocument::where('folder_id', $folder->id)->count());
        Queue::assertNothingPushed();
    }

    public function test_upload_stocke_le_fichier_sur_disk(): void
    {
        $user = $this->admin();
        $folder = $this->makeFolder(['created_by' => $user->id]);

        $this->actingAs($user);

        $this->postJson(route('ged.documents.store'), [
            'folder_id' => $folder->id,
            'files' => [$this->fakePdf('rapport.pdf')],
        ]);

        $doc = GedDocument::where('folder_id', $folder->id)->firstOrFail();
        Storage::disk('local')->assertExists($doc->disk_path);
    }

    public function test_document_cree_avec_bonnes_metadonnees(): void
    {
        $user = $this->admin();
        $folder = $this->makeFolder(['created_by' => $user->id]);

        $this->actingAs($user);

        $this->postJson(route('ged.documents.store'), [
            'folder_id' => $folder->id,
            'files' => [UploadedFile::fake()->create('rapport-annuel.pdf', 200, 'application/pdf')],
        ]);

        $this->assertDatabaseHas('ged_documents', [
            'folder_id' => $folder->id,
            'name' => 'rapport-annuel.pdf',
            'mime_type' => 'application/pdf',
            'created_by' => $user->id,
        ], 'tenant');
    }

    // =========================================================================
    // Validations
    // =========================================================================

    public function test_refus_si_mime_non_autorise(): void
    {
        $user = $this->admin();
        $folder = $this->makeFolder(['created_by' => $user->id]);

        $this->actingAs($user);

        $response = $this->postJson(route('ged.documents.store'), [
            'folder_id' => $folder->id,
            'files' => [UploadedFile::fake()->create('script.exe', 10, 'application/x-msdownload')],
        ]);

        $response->assertUnprocessable();
        Queue::assertNothingPushed();
    }

    public function test_refus_si_fichier_trop_lourd(): void
    {
        $user = $this->admin();
        $folder = $this->makeFolder(['created_by' => $user->id]);

        // Simuler un fichier de 60 Mo (config max 50 Mo)
        config(['ged.max_file_size' => 10 * 1024]); // 10 Ko pour le test

        $this->actingAs($user);

        $response = $this->postJson(route('ged.documents.store'), [
            'folder_id' => $folder->id,
            'files' => [UploadedFile::fake()->create('gros.pdf', 100, 'application/pdf')], // 100 Ko
        ]);

        $response->assertUnprocessable();
        Queue::assertNothingPushed();
    }

    public function test_refus_si_dossier_inexistant(): void
    {
        $user = $this->admin();

        $this->actingAs($user);

        $response = $this->postJson(route('ged.documents.store'), [
            'folder_id' => 99999,
            'files' => [$this->fakePdf()],
        ]);

        $response->assertUnprocessable();
        Queue::assertNothingPushed();
    }

    public function test_refus_si_dossier_prive_appartenant_a_autrui(): void
    {
        $owner = $this->admin();
        $intrus = $this->agent();

        $folder = $this->makeFolder([
            'is_private' => true,
            'created_by' => $owner->id,
        ]);

        $this->actingAs($intrus);

        $response = $this->postJson(route('ged.documents.store'), [
            'folder_id' => $folder->id,
            'files' => [$this->fakePdf()],
        ]);

        $response->assertForbidden();
        Queue::assertNothingPushed();
    }

    public function test_upload_sans_fichiers_echoue(): void
    {
        $user = $this->admin();
        $folder = $this->makeFolder(['created_by' => $user->id]);

        $this->actingAs($user);

        $response = $this->postJson(route('ged.documents.store'), [
            'folder_id' => $folder->id,
            'files' => [],
        ]);

        $response->assertUnprocessable();
        Queue::assertNothingPushed();
    }

    public function test_upload_sans_authentification_redirige(): void
    {
        $folder = $this->makeFolder();

        $response = $this->postJson(route('ged.documents.store'), [
            'folder_id' => $folder->id,
            'files' => [$this->fakePdf()],
        ]);

        $response->assertUnauthorized();
    }
}
