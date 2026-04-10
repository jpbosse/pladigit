<?php

namespace Tests\Feature\Ged;

use App\Enums\UserRole;
use App\Models\Tenant\GedDocument;
use App\Models\Tenant\GedDocumentVersion;
use App\Models\Tenant\GedFolder;
use App\Models\Tenant\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Tests fonctionnels — GED, versioning des documents.
 *
 * Couverture :
 *   - Re-upload du même nom → version 2 (version 1 archivée)
 *   - Re-upload répété → incrémentation correcte
 *   - Nom différent → deux documents distincts, aucune version archivée
 *   - Liste des versions → JSON correct
 *   - Téléchargement d'une version archivée → réponse streamed
 *   - Restauration d'une version → current_version incrémenté, ancienne archivée
 *   - Accès non authentifié → 401
 */
class GedVersioningTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    // ── Helpers ──────────────────────────────────────────────

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::ADMIN->value]);
    }

    private function makeFolder(User $user): GedFolder
    {
        return GedFolder::create([
            'name' => 'Dossier Test',
            'slug' => 'dossier-test-'.uniqid(),
            'path' => '/dossier-test',
            'parent_id' => null,
            'is_private' => false,
            'created_by' => $user->id,
        ]);
    }

    private function fakePdf(string $name = 'document.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 100, 'application/pdf');
    }

    private function upload(User $user, GedFolder $folder, UploadedFile $file): void
    {
        $this->actingAs($user)->postJson(route('ged.documents.store'), [
            'folder_id' => $folder->id,
            'files' => [$file],
        ])->assertOk();
    }

    // =========================================================================
    // Re-upload — versioning
    // =========================================================================

    public function test_reupload_meme_nom_cree_version_2(): void
    {
        $user = $this->admin();
        $folder = $this->makeFolder($user);

        // Premier upload
        $this->upload($user, $folder, $this->fakePdf('rapport.pdf'));

        $doc = GedDocument::where('folder_id', $folder->id)->where('name', 'rapport.pdf')->firstOrFail();
        $this->assertSame(1, $doc->current_version);
        $this->assertSame(0, GedDocumentVersion::where('document_id', $doc->id)->count());

        // Deuxième upload — même nom
        $this->upload($user, $folder, $this->fakePdf('rapport.pdf'));

        $doc->refresh();
        $this->assertSame(2, $doc->current_version);
        $this->assertSame(1, GedDocument::where('folder_id', $folder->id)->where('name', 'rapport.pdf')->count());
        $this->assertSame(1, GedDocumentVersion::where('document_id', $doc->id)->count());

        $archived = GedDocumentVersion::where('document_id', $doc->id)->first();
        $this->assertSame(1, $archived->version_number);
    }

    public function test_reupload_trois_fois_incremente_version(): void
    {
        $user = $this->admin();
        $folder = $this->makeFolder($user);

        $this->upload($user, $folder, $this->fakePdf('contrat.pdf'));
        $this->upload($user, $folder, $this->fakePdf('contrat.pdf'));
        $this->upload($user, $folder, $this->fakePdf('contrat.pdf'));

        $doc = GedDocument::where('folder_id', $folder->id)->where('name', 'contrat.pdf')->firstOrFail();
        $this->assertSame(3, $doc->current_version);
        $this->assertSame(1, GedDocument::where('folder_id', $folder->id)->where('name', 'contrat.pdf')->count());
        $this->assertSame(2, GedDocumentVersion::where('document_id', $doc->id)->count());

        $versionNumbers = GedDocumentVersion::where('document_id', $doc->id)
            ->pluck('version_number')
            ->sort()
            ->values()
            ->all();
        $this->assertSame([1, 2], $versionNumbers);
    }

    public function test_fichier_nom_different_cree_nouveau_document(): void
    {
        $user = $this->admin();
        $folder = $this->makeFolder($user);

        $this->upload($user, $folder, $this->fakePdf('doc-a.pdf'));
        $this->upload($user, $folder, $this->fakePdf('doc-b.pdf'));

        $this->assertSame(2, GedDocument::where('folder_id', $folder->id)->count());
        $this->assertSame(0, GedDocumentVersion::count());
    }

    // =========================================================================
    // Liste des versions
    // =========================================================================

    public function test_liste_versions_retourne_historique(): void
    {
        $user = $this->admin();
        $folder = $this->makeFolder($user);

        $this->upload($user, $folder, $this->fakePdf('acte.pdf'));
        $this->upload($user, $folder, $this->fakePdf('acte.pdf'));

        $doc = GedDocument::where('folder_id', $folder->id)->where('name', 'acte.pdf')->firstOrFail();

        $response = $this->actingAs($user)
            ->getJson(route('ged.documents.versions', $doc));

        $response->assertOk()
            ->assertJsonStructure(['current', 'versions'])
            ->assertJsonFragment(['version_number' => 2])   // version courante
            ->assertJsonPath('versions.0.version_number', 1); // version archivée
    }

    public function test_liste_versions_v1_retourne_current_sans_archives(): void
    {
        $user = $this->admin();
        $folder = $this->makeFolder($user);

        $this->upload($user, $folder, $this->fakePdf('rapport.pdf'));

        $doc = GedDocument::where('folder_id', $folder->id)->firstOrFail();

        $response = $this->actingAs($user)
            ->getJson(route('ged.documents.versions', $doc));

        $response->assertOk()
            ->assertJsonStructure(['current', 'versions'])
            ->assertJsonPath('current.version_number', 1)
            ->assertJsonCount(0, 'versions');
    }

    public function test_acces_versions_non_authentifie(): void
    {
        $user = $this->admin();
        $folder = $this->makeFolder($user);

        // Créer le document directement en DB pour ne pas actingAs()
        $doc = GedDocument::create([
            'folder_id' => $folder->id,
            'name' => 'test.pdf',
            'disk_path' => 'dossier-test/test.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1000,
            'created_by' => $user->id,
        ]);

        $this->getJson(route('ged.documents.versions', $doc))
            ->assertUnauthorized();
    }

    // =========================================================================
    // Téléchargement d'une version archivée
    // =========================================================================

    public function test_download_version_anterieure(): void
    {
        $user = $this->admin();
        $folder = $this->makeFolder($user);

        $this->upload($user, $folder, $this->fakePdf('facture.pdf'));
        $this->upload($user, $folder, $this->fakePdf('facture.pdf'));

        $doc = GedDocument::where('folder_id', $folder->id)->where('name', 'facture.pdf')->firstOrFail();
        $version = GedDocumentVersion::where('document_id', $doc->id)->where('version_number', 1)->firstOrFail();

        // S'assurer que le fichier archivé existe sur le disque fake
        Storage::disk('local')->put($version->disk_path, 'dummy content v1');

        $response = $this->actingAs($user)
            ->get(route('ged.documents.versions.download', [$doc, 1]));

        $response->assertOk();
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition') ?? '');
    }

    // =========================================================================
    // Restauration d'une version
    // =========================================================================

    public function test_restore_version_incremente_current_version(): void
    {
        $user = $this->admin();
        $folder = $this->makeFolder($user);

        $this->upload($user, $folder, $this->fakePdf('deliberation.pdf'));
        $this->upload($user, $folder, $this->fakePdf('deliberation.pdf'));

        $doc = GedDocument::where('folder_id', $folder->id)->where('name', 'deliberation.pdf')->firstOrFail();
        $this->assertSame(2, $doc->current_version);
        $this->assertSame(1, GedDocumentVersion::where('document_id', $doc->id)->count());

        $response = $this->actingAs($user)
            ->postJson(route('ged.documents.versions.restore', [$doc, 1]));

        $response->assertOk()->assertJsonFragment(['ok' => true]);

        $doc->refresh();
        $this->assertSame(3, $doc->current_version);

        // La v1 a été restaurée et supprimée des archives, la v2 a été archivée
        $this->assertSame(1, GedDocumentVersion::where('document_id', $doc->id)->count());
        $this->assertSame(2, GedDocumentVersion::where('document_id', $doc->id)->value('version_number'));
    }

    public function test_suppression_document_efface_fichiers_versions_archivees(): void
    {
        $user = $this->admin();
        $folder = $this->makeFolder($user);

        $this->upload($user, $folder, $this->fakePdf('contrat.pdf'));
        $this->upload($user, $folder, $this->fakePdf('contrat.pdf'));

        $doc = GedDocument::where('folder_id', $folder->id)->where('name', 'contrat.pdf')->firstOrFail();
        $version = GedDocumentVersion::where('document_id', $doc->id)->firstOrFail();

        // S'assurer que le fichier archivé existe sur le disque fake
        Storage::disk('local')->put($version->disk_path, 'contenu v1');
        Storage::disk('local')->assertExists($version->disk_path);

        $this->actingAs($user)
            ->deleteJson(route('ged.documents.destroy', $doc))
            ->assertOk();

        Storage::disk('local')->assertMissing($version->disk_path);
    }

    public function test_restore_interdit_si_non_proprietaire(): void
    {
        $owner = $this->admin();
        $intrus = User::factory()->create(['role' => UserRole::USER->value]);
        $folder = $this->makeFolder($owner);

        $this->upload($owner, $folder, $this->fakePdf('doc.pdf'));
        $this->upload($owner, $folder, $this->fakePdf('doc.pdf'));

        $doc = GedDocument::where('folder_id', $folder->id)->firstOrFail();

        $this->actingAs($intrus)
            ->postJson(route('ged.documents.versions.restore', [$doc, 1]))
            ->assertForbidden();
    }
}
