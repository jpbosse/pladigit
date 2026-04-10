<?php

namespace Tests\Feature\Ged;

use App\Models\Tenant\GedDocument;
use App\Models\Tenant\GedFolder;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Tests Feature — Renommage et déplacement de documents GED.
 */
class GedDocumentEditTest extends TestCase
{
    private User $admin;

    private User $agent;

    private GedFolder $folder;

    private GedFolder $otherFolder;

    private GedDocument $document;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->agent = User::factory()->create(['role' => 'user', 'status' => 'active']);

        $this->folder = GedFolder::create([
            'name' => 'RH',
            'slug' => 'rh',
            'path' => '/rh',
            'parent_id' => null,
            'is_private' => false,
            'created_by' => $this->admin->id,
        ]);

        $this->otherFolder = GedFolder::create([
            'name' => 'Juridique',
            'slug' => 'juridique',
            'path' => '/juridique',
            'parent_id' => null,
            'is_private' => false,
            'created_by' => $this->admin->id,
        ]);

        Storage::fake('local');

        $this->document = GedDocument::create([
            'folder_id' => $this->folder->id,
            'name' => 'contrat.pdf',
            'disk_path' => 'rh/uuid-test.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'current_version' => 1,
            'created_by' => $this->admin->id,
        ]);
    }

    // ── Renommage ─────────────────────────────────────────────────────────────

    public function test_renommer_document_met_a_jour_le_nom(): void
    {
        $this->actingAs($this->admin, 'tenant')
            ->patchJson(route('ged.documents.update', $this->document), ['name' => 'contrat_2024.pdf'])
            ->assertOk()
            ->assertJson(['ok' => true, 'name' => 'contrat_2024.pdf']);

        $this->assertDatabaseHas('ged_documents', [
            'id' => $this->document->id,
            'name' => 'contrat_2024.pdf',
        ], 'tenant');
    }

    public function test_renommage_refusé_sans_authentification(): void
    {
        $this->patchJson(route('ged.documents.update', $this->document), ['name' => 'nouveau.pdf'])
            ->assertUnauthorized();
    }

    public function test_renommage_refusé_si_nom_vide(): void
    {
        $this->actingAs($this->admin, 'tenant')
            ->patchJson(route('ged.documents.update', $this->document), ['name' => ''])
            ->assertUnprocessable();
    }

    public function test_agent_peut_renommer_son_propre_document(): void
    {
        // L'agent est créateur du dossier → il a Admin sur ce dossier
        $ownFolder = GedFolder::create([
            'name' => 'Perso',
            'slug' => 'perso',
            'path' => '/perso',
            'parent_id' => null,
            'is_private' => false,
            'created_by' => $this->agent->id,
        ]);

        $doc = GedDocument::create([
            'folder_id' => $ownFolder->id,
            'name' => 'mon-doc.pdf',
            'disk_path' => 'perso/uuid-agent.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 512,
            'current_version' => 1,
            'created_by' => $this->agent->id,
        ]);

        $this->actingAs($this->agent, 'tenant')
            ->patchJson(route('ged.documents.update', $doc), ['name' => 'mon-doc-v2.pdf'])
            ->assertOk();
    }

    public function test_agent_ne_peut_pas_renommer_document_dautrui(): void
    {
        $this->actingAs($this->agent, 'tenant')
            ->patchJson(route('ged.documents.update', $this->document), ['name' => 'piraté.pdf'])
            ->assertForbidden();
    }

    // ── Déplacement ───────────────────────────────────────────────────────────

    public function test_déplacer_document_met_a_jour_folder_id(): void
    {
        $this->actingAs($this->admin, 'tenant')
            ->postJson(route('ged.documents.move', $this->document), [
                'target_folder_id' => $this->otherFolder->id,
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('ged_documents', [
            'id' => $this->document->id,
            'folder_id' => $this->otherFolder->id,
        ], 'tenant');
    }

    public function test_déplacer_vers_le_même_dossier_est_un_no_op(): void
    {
        $this->actingAs($this->admin, 'tenant')
            ->postJson(route('ged.documents.move', $this->document), [
                'target_folder_id' => $this->folder->id,
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('ged_documents', [
            'id' => $this->document->id,
            'folder_id' => $this->folder->id,
        ], 'tenant');
    }

    public function test_déplacement_refusé_sans_authentification(): void
    {
        $this->postJson(route('ged.documents.move', $this->document), [
            'target_folder_id' => $this->otherFolder->id,
        ])->assertUnauthorized();
    }

    public function test_déplacement_refusé_si_dossier_cible_inexistant(): void
    {
        $this->actingAs($this->admin, 'tenant')
            ->postJson(route('ged.documents.move', $this->document), [
                'target_folder_id' => 99999,
            ])
            ->assertUnprocessable();
    }

    public function test_agent_ne_peut_pas_déplacer_document_dautrui(): void
    {
        $this->actingAs($this->agent, 'tenant')
            ->postJson(route('ged.documents.move', $this->document), [
                'target_folder_id' => $this->otherFolder->id,
            ])
            ->assertForbidden();
    }
}
