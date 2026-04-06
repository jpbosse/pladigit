<?php

namespace Tests\Feature\Ged;

use App\Models\Tenant\GedDocument;
use App\Models\Tenant\GedFolder;
use App\Models\Tenant\User;
use Tests\TestCase;

/**
 * Tests Feature — Recherche dans la GED.
 */
class GedSearchTest extends TestCase
{
    private User $admin;

    private GedFolder $folder;

    private GedDocument $document;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $this->folder = GedFolder::create([
            'name' => 'Marchés publics',
            'slug' => 'marches-publics',
            'path' => '/marches-publics',
            'parent_id' => null,
            'is_private' => false,
            'created_by' => $this->admin->id,
        ]);

        $this->document = GedDocument::create([
            'folder_id' => $this->folder->id,
            'name' => 'rapport_annuel_2024.pdf',
            'disk_path' => 'marches-publics/uuid-rapport.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 2048,
            'current_version' => 1,
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_page_recherche_accessible(): void
    {
        $this->actingAs($this->admin, 'tenant')
            ->get(route('ged.search'))
            ->assertOk();
    }

    public function test_recherche_sans_terme_retourne_page_vide(): void
    {
        $this->actingAs($this->admin, 'tenant')
            ->get(route('ged.search'))
            ->assertOk()
            ->assertViewHas('q', '');
    }

    public function test_recherche_trouve_un_document_par_nom(): void
    {
        $response = $this->actingAs($this->admin, 'tenant')
            ->get(route('ged.search', ['q' => 'rapport']))
            ->assertOk();

        $this->assertTrue($response->viewData('documents')->contains('id', $this->document->id));
    }

    public function test_recherche_trouve_un_dossier_par_nom(): void
    {
        $response = $this->actingAs($this->admin, 'tenant')
            ->get(route('ged.search', ['q' => 'march']))
            ->assertOk();

        $this->assertTrue($response->viewData('folders')->contains('id', $this->folder->id));
    }

    public function test_recherche_ne_retourne_pas_les_documents_hors_périmètre(): void
    {
        $agent = User::factory()->create(['role' => 'user', 'status' => 'active']);
        $privateFolder = GedFolder::create([
            'name' => 'Confidentiel',
            'slug' => 'confidentiel',
            'path' => '/confidentiel',
            'parent_id' => null,
            'is_private' => true,
            'created_by' => $this->admin->id,
        ]);
        $privateDoc = GedDocument::create([
            'folder_id' => $privateFolder->id,
            'name' => 'rapport_secret.pdf',
            'disk_path' => 'confidentiel/uuid-secret.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 512,
            'current_version' => 1,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($agent, 'tenant')
            ->get(route('ged.search', ['q' => 'rapport']));

        $response->assertOk();
        $documents = $response->viewData('documents');
        $this->assertFalse($documents->contains('id', $privateDoc->id));
    }

    public function test_recherche_retourne_résultats_insensible_à_la_casse(): void
    {
        $response = $this->actingAs($this->admin, 'tenant')
            ->get(route('ged.search', ['q' => 'RAPPORT']))
            ->assertOk();

        $this->assertTrue($response->viewData('documents')->contains('id', $this->document->id));
    }

    public function test_recherche_non_authentifié_redirige(): void
    {
        $this->get(route('ged.search', ['q' => 'rapport']))
            ->assertRedirect(route('login'));
    }

    public function test_endpoint_folders_all_retourne_liste_plate(): void
    {
        $this->actingAs($this->admin, 'tenant')
            ->getJson(route('ged.folders.all'))
            ->assertOk()
            ->assertJsonStructure(['folders' => [['id', 'name', 'path']]])
            ->assertJsonFragment(['id' => $this->folder->id]);
    }
}
