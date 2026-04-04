<?php

namespace Tests\Feature\Ged;

use App\Enums\UserRole;
use App\Models\Tenant\GedDocument;
use App\Models\Tenant\GedFolder;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Tests fonctionnels — GED, téléchargement et prévisualisation.
 *
 * Couverture :
 *   - Download force-téléchargement (Content-Disposition: attachment)
 *   - Serve inline PDF (Content-Disposition: inline)
 *   - Serve inline image (Content-Disposition: inline)
 *   - Serve d'un fichier non prévisualisable → redirection vers download
 *   - 404 si fichier absent du stockage
 *   - 403 si dossier privé d'autrui
 *   - Suppression document (soft delete + fichier physique)
 */
class GedDownloadTest extends TestCase
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

    private function agent(): User
    {
        return User::factory()->create(['role' => UserRole::USER->value]);
    }

    private function makeFolder(array $attrs = []): GedFolder
    {
        return GedFolder::create(array_merge([
            'name' => 'Dossier Test',
            'slug' => 'dossier-test',
            'path' => '/dossier-test',
            'parent_id' => null,
            'is_private' => false,
            'created_by' => $attrs['created_by'] ?? $this->admin()->id,
        ], $attrs));
    }

    /**
     * Crée un GedDocument avec le fichier présent sur le faux disk local.
     */
    private function makeDocument(GedFolder $folder, User $user, string $mime = 'application/pdf'): GedDocument
    {
        $diskPath = 'ged/test-org/2026/03/{uuid}.pdf';

        // Écrire le contenu sur le faux disk
        Storage::disk('local')->put($diskPath, '%PDF-1.4 fake content');

        return GedDocument::create([
            'folder_id' => $folder->id,
            'name' => 'document.pdf',
            'disk_path' => $diskPath,
            'mime_type' => $mime,
            'size_bytes' => 100,
            'current_version' => 1,
            'created_by' => $user->id,
        ]);
    }

    // =========================================================================
    // Download
    // =========================================================================

    public function test_download_retourne_le_fichier_en_attachment(): void
    {
        $user = $this->admin();
        $folder = $this->makeFolder(['created_by' => $user->id]);
        $document = $this->makeDocument($folder, $user);

        $this->actingAs($user);

        $response = $this->get(route('ged.documents.download', $document));

        $response->assertOk();
        $this->assertStringContainsString(
            'attachment',
            $response->headers->get('Content-Disposition', '')
        );
    }

    public function test_download_retourne_le_bon_nom_de_fichier(): void
    {
        $user = $this->admin();
        $folder = $this->makeFolder(['created_by' => $user->id]);
        $document = $this->makeDocument($folder, $user);

        $this->actingAs($user);

        $response = $this->get(route('ged.documents.download', $document));

        $disposition = $response->headers->get('Content-Disposition', '');
        $this->assertStringContainsString($document->name, $disposition);
    }

    public function test_download_404_si_fichier_absent_du_stockage(): void
    {
        $user = $this->admin();
        $folder = $this->makeFolder(['created_by' => $user->id]);

        // Document pointant vers un fichier inexistant
        $document = GedDocument::create([
            'folder_id' => $folder->id,
            'name' => 'fantome.pdf',
            'disk_path' => 'ged/test/fantome.pdf', // pas sur le disk fake
            'mime_type' => 'application/pdf',
            'size_bytes' => 0,
            'current_version' => 1,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user);

        $response = $this->get(route('ged.documents.download', $document));

        $response->assertNotFound();
    }

    public function test_download_403_si_dossier_prive_d_autrui(): void
    {
        $owner = $this->admin();
        $intrus = $this->agent();

        $folder = $this->makeFolder(['is_private' => true, 'created_by' => $owner->id]);
        $document = $this->makeDocument($folder, $owner);

        $this->actingAs($intrus);

        $response = $this->get(route('ged.documents.download', $document));

        $response->assertForbidden();
    }

    // =========================================================================
    // Serve inline
    // =========================================================================

    public function test_serve_pdf_retourne_content_disposition_inline(): void
    {
        $user = $this->admin();
        $folder = $this->makeFolder(['created_by' => $user->id]);
        $document = $this->makeDocument($folder, $user, 'application/pdf');

        $this->actingAs($user);

        $response = $this->get(route('ged.documents.serve', $document));

        $response->assertOk();
        $this->assertStringContainsString(
            'inline',
            $response->headers->get('Content-Disposition', '')
        );
    }

    public function test_serve_image_retourne_content_disposition_inline(): void
    {
        $user = $this->admin();
        $folder = $this->makeFolder(['created_by' => $user->id]);

        $diskPath = 'ged/test-org/2026/03/image.jpg';
        Storage::disk('local')->put($diskPath, 'fake image content');

        $document = GedDocument::create([
            'folder_id' => $folder->id,
            'name' => 'photo.jpg',
            'disk_path' => $diskPath,
            'mime_type' => 'image/jpeg',
            'size_bytes' => 50,
            'current_version' => 1,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user);

        $response = $this->get(route('ged.documents.serve', $document));

        $response->assertOk();
        $this->assertStringContainsString('inline', $response->headers->get('Content-Disposition', ''));
    }

    public function test_serve_fichier_non_previewable_redirige_vers_download(): void
    {
        $user = $this->admin();
        $folder = $this->makeFolder(['created_by' => $user->id]);

        $diskPath = 'ged/test-org/2026/03/archive.zip';
        Storage::disk('local')->put($diskPath, 'PK fake zip content');

        $document = GedDocument::create([
            'folder_id' => $folder->id,
            'name' => 'archive.zip',
            'disk_path' => $diskPath,
            'mime_type' => 'application/zip',
            'size_bytes' => 200,
            'current_version' => 1,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user);

        $response = $this->get(route('ged.documents.serve', $document));

        $response->assertRedirect(route('ged.documents.download', $document));
    }

    // =========================================================================
    // Suppression
    // =========================================================================

    public function test_supprimer_document_soft_delete_et_fichier(): void
    {
        $user = $this->admin();
        $folder = $this->makeFolder(['created_by' => $user->id]);
        $document = $this->makeDocument($folder, $user);
        $diskPath = $document->disk_path;

        $this->actingAs($user);

        $response = $this->deleteJson(route('ged.documents.destroy', $document));

        $response->assertOk()->assertJsonFragment(['ok' => true]);

        // Soft delete en base
        $this->assertSoftDeleted('ged_documents', ['id' => $document->id], 'tenant');

        // Fichier physique supprimé
        Storage::disk('local')->assertMissing($diskPath);
    }

    public function test_suppression_refusee_si_pas_le_createur(): void
    {
        $owner = $this->admin();
        $intrus = $this->agent();
        $folder = $this->makeFolder(['created_by' => $owner->id]);
        $document = $this->makeDocument($folder, $owner);

        $this->actingAs($intrus);

        $response = $this->deleteJson(route('ged.documents.destroy', $document));

        $response->assertForbidden();
        $this->assertDatabaseHas('ged_documents', ['id' => $document->id, 'deleted_at' => null], 'tenant');
    }

    public function test_suppression_autorisee_pour_admin_meme_si_pas_createur(): void
    {
        $createur = $this->agent();
        $admin = $this->admin();
        $folder = $this->makeFolder(['created_by' => $createur->id]);
        $document = $this->makeDocument($folder, $createur);

        $this->actingAs($admin);

        $response = $this->deleteJson(route('ged.documents.destroy', $document));

        $response->assertOk()->assertJsonFragment(['ok' => true]);
        $this->assertSoftDeleted('ged_documents', ['id' => $document->id], 'tenant');
    }
}
