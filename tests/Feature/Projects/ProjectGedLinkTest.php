<?php

namespace Tests\Feature\Projects;

use App\Enums\GedPermissionLevel;
use App\Enums\ProjectRole;
use App\Enums\UserRole;
use App\Models\Tenant\GedDocument;
use App\Models\Tenant\GedFolder;
use App\Models\Tenant\GedFolderUserPermission;
use App\Models\Tenant\Project;
use App\Models\Tenant\ProjectGedLink;
use App\Models\Tenant\ProjectMember;
use App\Models\Tenant\Task;
use App\Models\Tenant\User;
use Tests\TestCase;

/**
 * Tests Feature — Intégration GED ↔ Projets (Jalon 6).
 *
 * Couverture :
 *   - Lier un document GED à un projet (owner membre)
 *   - Refus si l'utilisateur n'est pas membre éditeur du projet
 *   - Refus si l'utilisateur n'a pas au moins View sur le document GED
 *   - Doublons refusés (même doc lié deux fois)
 *   - Délier un document GED
 *   - Lier un document GED à une tâche (via task_id)
 *   - Liste des liens (GET ged-links)
 *   - Picker AJAX : dossiers racine
 *   - Section "Projets liés" : GedDocument::linkedProjects()
 */
class ProjectGedLinkTest extends TestCase
{
    // ── Factories ─────────────────────────────────────────────────────────

    private function makeAdmin(): User
    {
        return User::factory()->create(['role' => UserRole::ADMIN->value, 'status' => 'active']);
    }

    private function makeAgent(): User
    {
        return User::factory()->create(['role' => UserRole::USER->value, 'status' => 'active']);
    }

    private function makeProject(User $owner, array $attrs = []): Project
    {
        $project = Project::on('tenant')->create(array_merge([
            'created_by' => $owner->id,
            'name' => 'Projet GED Test',
            'status' => 'active',
            'start_date' => '2026-01-01',
            'due_date' => '2026-12-31',
            'color' => '#1E3A5F',
            'is_private' => false,
        ], $attrs));

        ProjectMember::on('tenant')->create([
            'project_id' => $project->id,
            'user_id' => $owner->id,
            'role' => ProjectRole::OWNER->value,
        ]);

        return $project;
    }

    private function makeTask(Project $project, User $creator): Task
    {
        return Task::on('tenant')->create([
            'project_id' => $project->id,
            'created_by' => $creator->id,
            'title' => 'Tâche test',
            'status' => 'todo',
            'priority' => 'medium',
        ]);
    }

    private function makeFolder(User $owner, array $attrs = []): GedFolder
    {
        return GedFolder::on('tenant')->create(array_merge([
            'name' => 'Dossier Test',
            'slug' => 'dossier-test-'.uniqid(),
            'path' => '/dossier-test',
            'parent_id' => null,
            'is_private' => false,
            'created_by' => $owner->id,
        ], $attrs));
    }

    private function makeDocument(GedFolder $folder, User $creator, array $attrs = []): GedDocument
    {
        return GedDocument::on('tenant')->create(array_merge([
            'folder_id' => $folder->id,
            'name' => 'document-test.pdf',
            'disk_path' => 'ged/document-test.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 102400,
            'current_version' => 1,
            'created_by' => $creator->id,
        ], $attrs));
    }

    // ── Tests : liaison projet ────────────────────────────────────────────

    public function test_owner_peut_lier_un_document_ged_au_projet(): void
    {
        $owner = $this->makeAdmin();
        $project = $this->makeProject($owner);
        $folder = $this->makeFolder($owner);
        $doc = $this->makeDocument($folder, $owner);

        $this->actingAs($owner);

        $response = $this->postJson(route('projects.ged_links.store', $project), [
            'ged_document_id' => $doc->id,
        ]);

        $response->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('project_ged_links', [
            'documentable_type' => Project::class,
            'documentable_id' => $project->id,
            'ged_document_id' => $doc->id,
            'linked_by' => $owner->id,
        ], 'tenant');
    }

    public function test_viewer_ne_peut_pas_lier_un_document_ged(): void
    {
        $owner = $this->makeAdmin();
        $viewer = $this->makeAgent();
        $project = $this->makeProject($owner);

        // viewer membre avec rôle Viewer
        ProjectMember::on('tenant')->create([
            'project_id' => $project->id,
            'user_id' => $viewer->id,
            'role' => ProjectRole::VIEWER->value,
        ]);

        $folder = $this->makeFolder($owner);
        $doc = $this->makeDocument($folder, $owner);

        $this->actingAs($viewer);

        $this->postJson(route('projects.ged_links.store', $project), [
            'ged_document_id' => $doc->id,
        ])->assertForbidden();
    }

    public function test_non_membre_ne_peut_pas_lier_un_document_ged(): void
    {
        $owner = $this->makeAdmin();
        $outsider = $this->makeAgent();
        $project = $this->makeProject($owner);

        $folder = $this->makeFolder($owner);
        $doc = $this->makeDocument($folder, $owner);

        $this->actingAs($outsider);

        $this->postJson(route('projects.ged_links.store', $project), [
            'ged_document_id' => $doc->id,
        ])->assertForbidden();
    }

    public function test_refus_si_acces_ged_insuffisant(): void
    {
        $owner = $this->makeAdmin();
        $member = $this->makeAgent();
        $project = $this->makeProject($owner);

        // member est membre éditeur du projet
        ProjectMember::on('tenant')->create([
            'project_id' => $project->id,
            'user_id' => $member->id,
            'role' => ProjectRole::MEMBER->value,
        ]);

        // Dossier GED avec permission None pour le member
        $folder = $this->makeFolder($owner, ['is_private' => true]);
        GedFolderUserPermission::on('tenant')->create([
            'folder_id' => $folder->id,
            'user_id' => $member->id,
            'level' => GedPermissionLevel::None->value,
        ]);
        $doc = $this->makeDocument($folder, $owner);

        $this->actingAs($member);

        $this->postJson(route('projects.ged_links.store', $project), [
            'ged_document_id' => $doc->id,
        ])->assertForbidden();
    }

    public function test_doublon_est_refuse(): void
    {
        $owner = $this->makeAdmin();
        $project = $this->makeProject($owner);
        $folder = $this->makeFolder($owner);
        $doc = $this->makeDocument($folder, $owner);

        ProjectGedLink::on('tenant')->create([
            'documentable_type' => Project::class,
            'documentable_id' => $project->id,
            'ged_document_id' => $doc->id,
            'linked_by' => $owner->id,
        ]);

        $this->actingAs($owner);

        $this->postJson(route('projects.ged_links.store', $project), [
            'ged_document_id' => $doc->id,
        ])->assertStatus(422)->assertJsonPath('message', 'Ce document est déjà lié.');
    }

    // ── Tests : déliaison ─────────────────────────────────────────────────

    public function test_owner_peut_delirer_un_lien_ged(): void
    {
        $owner = $this->makeAdmin();
        $project = $this->makeProject($owner);
        $folder = $this->makeFolder($owner);
        $doc = $this->makeDocument($folder, $owner);

        $link = ProjectGedLink::on('tenant')->create([
            'documentable_type' => Project::class,
            'documentable_id' => $project->id,
            'ged_document_id' => $doc->id,
            'linked_by' => $owner->id,
        ]);

        $this->actingAs($owner);

        $this->deleteJson(route('projects.ged_links.destroy', [$project, $link]))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('project_ged_links', ['id' => $link->id], 'tenant');
    }

    public function test_non_membre_ne_peut_pas_delirer(): void
    {
        $owner = $this->makeAdmin();
        $outsider = $this->makeAgent();
        $project = $this->makeProject($owner);
        $folder = $this->makeFolder($owner);
        $doc = $this->makeDocument($folder, $owner);

        $link = ProjectGedLink::on('tenant')->create([
            'documentable_type' => Project::class,
            'documentable_id' => $project->id,
            'ged_document_id' => $doc->id,
            'linked_by' => $owner->id,
        ]);

        $this->actingAs($outsider);

        $this->deleteJson(route('projects.ged_links.destroy', [$project, $link]))
            ->assertForbidden();
    }

    // ── Tests : liaison tâche ─────────────────────────────────────────────

    public function test_owner_peut_lier_un_document_ged_a_une_tache(): void
    {
        $owner = $this->makeAdmin();
        $project = $this->makeProject($owner);
        $task = $this->makeTask($project, $owner);
        $folder = $this->makeFolder($owner);
        $doc = $this->makeDocument($folder, $owner);

        $this->actingAs($owner);

        $response = $this->postJson(route('projects.ged_links.store', $project), [
            'ged_document_id' => $doc->id,
            'task_id' => $task->id,
        ]);

        $response->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('project_ged_links', [
            'documentable_type' => Task::class,
            'documentable_id' => $task->id,
            'ged_document_id' => $doc->id,
        ], 'tenant');
    }

    // ── Tests : liste ─────────────────────────────────────────────────────

    public function test_liste_des_liens_ged_du_projet(): void
    {
        $owner = $this->makeAdmin();
        $project = $this->makeProject($owner);
        $folder = $this->makeFolder($owner);
        $doc1 = $this->makeDocument($folder, $owner);
        $doc2 = $this->makeDocument($folder, $owner, ['name' => 'autre.pdf']);

        ProjectGedLink::on('tenant')->create([
            'documentable_type' => Project::class,
            'documentable_id' => $project->id,
            'ged_document_id' => $doc1->id,
            'linked_by' => $owner->id,
        ]);
        ProjectGedLink::on('tenant')->create([
            'documentable_type' => Project::class,
            'documentable_id' => $project->id,
            'ged_document_id' => $doc2->id,
            'linked_by' => $owner->id,
        ]);

        $this->actingAs($owner);

        $response = $this->getJson(route('projects.ged_links.index', $project));

        $response->assertOk();
        $this->assertCount(2, $response->json('links'));
    }

    // ── Tests : picker ────────────────────────────────────────────────────

    public function test_picker_retourne_les_dossiers_racine(): void
    {
        $owner = $this->makeAdmin();
        $project = $this->makeProject($owner);
        $folder = $this->makeFolder($owner);

        $this->actingAs($owner);

        $response = $this->getJson(route('projects.ged_links.picker', $project));

        $response->assertOk()
            ->assertJsonStructure(['folder_id', 'folders', 'documents']);

        $folderIds = collect($response->json('folders'))->pluck('id');
        $this->assertTrue($folderIds->contains($folder->id));
    }

    // ── Tests : linkedProjects() sur GedDocument ──────────────────────────

    public function test_ged_document_retourne_ses_projets_lies(): void
    {
        $owner = $this->makeAdmin();
        $project = $this->makeProject($owner);
        $folder = $this->makeFolder($owner);
        $doc = $this->makeDocument($folder, $owner);

        ProjectGedLink::on('tenant')->create([
            'documentable_type' => Project::class,
            'documentable_id' => $project->id,
            'ged_document_id' => $doc->id,
            'linked_by' => $owner->id,
        ]);

        $doc->load('projectLinks.documentable');

        $linked = $doc->linkedProjects();

        $this->assertCount(1, $linked);
        $this->assertEquals($project->id, $linked->first()->id);
    }
}
