<?php

namespace Tests\Feature\Ged;

use App\Enums\UserRole;
use App\Models\Tenant\GedDocument;
use App\Models\Tenant\GedFolder;
use App\Models\Tenant\GedWopiToken;
use App\Models\Tenant\User;
use App\Services\Ged\WopiTokenService;
use App\Services\TenantManager;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Tests fonctionnels — Collabora Online, Jalon 1 (lecture seule).
 *
 * Couverture :
 *   - WopiTokenService : génération + validation + expiration
 *   - WopiController::checkFileInfo() : 200, 401 (token invalide), 401 (token expiré)
 *   - WopiController::getFile()       : 200, 401 (token invalide), 404 (fichier absent)
 *   - GedEditorController::show()     : redirect si Collabora non configuré
 *   - GedEditorController::show()     : redirect si MIME non supporté
 *   - GedEditorController::show()     : vue editor retournée si tout est ok
 *   - isCollaboraSupported()          : true / false selon config et MIME
 */
class GedCollaboraTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    // ── Helpers ──────────────────────────────────────────────

    private function orgSlug(): string
    {
        return app(TenantManager::class)->currentOrFail()->slug;
    }

    private function accessToken(GedWopiToken $token): string
    {
        return app(WopiTokenService::class)->buildAccessToken($token, $this->orgSlug());
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::ADMIN->value]);
    }

    private function makeFolder(User $user): GedFolder
    {
        return GedFolder::create([
            'name' => 'Dossier Test',
            'slug' => 'dossier-test',
            'path' => '/dossier-test',
            'parent_id' => null,
            'is_private' => false,
            'created_by' => $user->id,
        ]);
    }

    private function makeDocument(GedFolder $folder, User $user, string $mime = 'application/vnd.oasis.opendocument.text'): GedDocument
    {
        $path = 'ged/test-org/2026/06/test.odt';
        Storage::disk('local')->put($path, 'fake odt content');

        return GedDocument::create([
            'folder_id' => $folder->id,
            'name' => 'document.odt',
            'disk_path' => $path,
            'mime_type' => $mime,
            'size_bytes' => 16,
            'current_version' => 1,
            'created_by' => $user->id,
        ]);
    }

    // ── WopiTokenService ─────────────────────────────────────

    public function test_generate_cree_un_token_en_base(): void
    {
        $user = $this->admin();
        $folder = $this->makeFolder($user);
        $doc = $this->makeDocument($folder, $user);

        $service = app(WopiTokenService::class);
        $token = $service->generate($doc, $user);

        $this->assertDatabaseHas('ged_wopi_tokens', [
            'document_id' => $doc->id,
            'user_id' => $user->id,
            'token' => $token->token,
        ], 'tenant');
        $this->assertFalse($token->isExpired());
    }

    public function test_validate_retourne_le_token_valide(): void
    {
        $user = $this->admin();
        $folder = $this->makeFolder($user);
        $doc = $this->makeDocument($folder, $user);

        $service = app(WopiTokenService::class);
        $token = $service->generate($doc, $user);

        $found = $service->validate($token->token);

        $this->assertNotNull($found);
        $this->assertEquals($token->id, $found->id);
    }

    public function test_validate_retourne_null_pour_token_inconnu(): void
    {
        $service = app(WopiTokenService::class);

        $this->assertNull($service->validate('tokeninexistant'));
    }

    public function test_validate_retourne_null_pour_token_expire(): void
    {
        $user = $this->admin();
        $folder = $this->makeFolder($user);
        $doc = $this->makeDocument($folder, $user);

        $wopiToken = GedWopiToken::create([
            'document_id' => $doc->id,
            'user_id' => $user->id,
            'token' => 'expired-token-abc',
            'expires_at' => now()->subMinutes(5),
        ]);

        $service = app(WopiTokenService::class);
        $this->assertNull($service->validate($wopiToken->token));
    }

    // ── WopiController::checkFileInfo ────────────────────────

    public function test_check_file_info_retourne_200_avec_metadonnees(): void
    {
        $user = $this->admin();
        $folder = $this->makeFolder($user);
        $doc = $this->makeDocument($folder, $user);

        $service = app(WopiTokenService::class);
        $token = $service->generate($doc, $user);

        $response = $this->getJson(route('wopi.files.info', $doc->id).'?access_token='.$this->accessToken($token));

        $response->assertOk()
            ->assertJsonFragment([
                'BaseFileName' => $doc->name,
                'Size' => $doc->size_bytes,
                'UserCanWrite' => true,
                'ReadOnly' => false,
            ]);
    }

    public function test_check_file_info_401_si_token_invalide(): void
    {
        $user = $this->admin();
        $folder = $this->makeFolder($user);
        $doc = $this->makeDocument($folder, $user);

        $response = $this->getJson(route('wopi.files.info', $doc->id).'?access_token=mauvais');

        $response->assertStatus(401);
    }

    public function test_check_file_info_401_si_token_appartient_a_un_autre_document(): void
    {
        $user = $this->admin();
        $folder = $this->makeFolder($user);
        $doc1 = $this->makeDocument($folder, $user);
        $doc2 = $this->makeDocument($folder, $user);

        $service = app(WopiTokenService::class);
        $token = $service->generate($doc1, $user);

        // Token généré pour doc1, utilisé sur doc2
        $response = $this->getJson(route('wopi.files.info', $doc2->id).'?access_token='.$this->accessToken($token));

        $response->assertStatus(401);
    }

    // ── WopiController::getFile ───────────────────────────────

    public function test_get_file_retourne_le_contenu_binaire(): void
    {
        $user = $this->admin();
        $folder = $this->makeFolder($user);
        $doc = $this->makeDocument($folder, $user);

        $service = app(WopiTokenService::class);
        $token = $service->generate($doc, $user);

        $response = $this->get(route('wopi.files.contents', $doc->id).'?access_token='.$this->accessToken($token));

        $response->assertOk();
        $this->assertEquals('fake odt content', $response->streamedContent());
    }

    public function test_get_file_401_si_token_invalide(): void
    {
        $user = $this->admin();
        $folder = $this->makeFolder($user);
        $doc = $this->makeDocument($folder, $user);

        $response = $this->get(route('wopi.files.contents', $doc->id).'?access_token=mauvais');

        $response->assertStatus(401);
    }

    public function test_get_file_404_si_fichier_absent_du_stockage(): void
    {
        $user = $this->admin();
        $folder = $this->makeFolder($user);

        $doc = GedDocument::create([
            'folder_id' => $folder->id,
            'name' => 'fantome.odt',
            'disk_path' => 'ged/test-org/2026/06/fantome.odt',
            'mime_type' => 'application/vnd.oasis.opendocument.text',
            'size_bytes' => 0,
            'current_version' => 1,
            'created_by' => $user->id,
        ]);

        $service = app(WopiTokenService::class);
        $token = $service->generate($doc, $user);

        $response = $this->get(route('wopi.files.contents', $doc->id).'?access_token='.$this->accessToken($token));

        $response->assertNotFound();
    }

    // ── GedEditorController ───────────────────────────────────

    public function test_editor_redirige_si_collabora_non_configure(): void
    {
        config(['collabora.url' => '']);

        $user = $this->admin();
        $folder = $this->makeFolder($user);
        $doc = $this->makeDocument($folder, $user);

        $this->actingAs($user);

        $response = $this->get(route('ged.documents.editor', $doc));

        $response->assertRedirect(route('ged.folders.show', $doc->folder_id));
        $response->assertSessionHas('error');
    }

    public function test_editor_redirige_si_mime_non_supporte(): void
    {
        config(['collabora.url' => 'https://collabora.test']);

        $user = $this->admin();
        $folder = $this->makeFolder($user);
        $doc = $this->makeDocument($folder, $user, 'application/pdf');

        $this->actingAs($user);

        $response = $this->get(route('ged.documents.editor', $doc));

        $response->assertRedirect(route('ged.folders.show', $doc->folder_id));
        $response->assertSessionHas('error');
    }

    public function test_editor_retourne_la_vue_si_collabora_configure(): void
    {
        config(['collabora.url' => 'https://collabora.test']);

        $user = $this->admin();
        $folder = $this->makeFolder($user);
        $doc = $this->makeDocument($folder, $user);

        $this->actingAs($user);

        $response = $this->get(route('ged.documents.editor', $doc));

        $response->assertOk();
        $response->assertViewIs('ged.editor');
        $response->assertViewHas('document', $doc);
        $response->assertViewHas('actionUrl');
        $response->assertViewHas('accessToken');
        $this->assertStringContainsString('collabora.test', $response->viewData('actionUrl'));
    }

    // ── isCollaboraSupported ──────────────────────────────────

    public function test_is_collabora_supported_true_pour_odt(): void
    {
        config(['collabora.url' => 'https://collabora.test']);

        $user = $this->admin();
        $folder = $this->makeFolder($user);
        $doc = $this->makeDocument($folder, $user, 'application/vnd.oasis.opendocument.text');

        $this->assertTrue($doc->isCollaboraSupported());
    }

    public function test_is_collabora_supported_false_si_collabora_non_configure(): void
    {
        config(['collabora.url' => '']);

        $user = $this->admin();
        $folder = $this->makeFolder($user);
        $doc = $this->makeDocument($folder, $user, 'application/vnd.oasis.opendocument.text');

        $this->assertFalse($doc->isCollaboraSupported());
    }

    public function test_is_collabora_supported_false_pour_mime_non_supporte(): void
    {
        config(['collabora.url' => 'https://collabora.test']);

        $user = $this->admin();
        $folder = $this->makeFolder($user);
        $doc = $this->makeDocument($folder, $user, 'image/jpeg');

        $this->assertFalse($doc->isCollaboraSupported());
    }
}
