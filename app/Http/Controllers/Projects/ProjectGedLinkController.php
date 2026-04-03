<?php

namespace App\Http\Controllers\Projects;

use App\Enums\GedPermissionLevel;
use App\Http\Controllers\Controller;
use App\Models\Tenant\GedDocument;
use App\Models\Tenant\GedFolder;
use App\Models\Tenant\Project;
use App\Models\Tenant\ProjectGedLink;
use App\Models\Tenant\Task;
use App\Models\Tenant\User;
use App\Services\Ged\GedPermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectGedLinkController extends Controller
{
    public function __construct(private readonly GedPermissionService $gedPerms) {}

    // ── Liste des documents GED liés ──────────────────────────────────────

    public function index(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $target = $this->resolveTarget($project, $request);

        $links = ProjectGedLink::on('tenant')
            ->where('documentable_type', get_class($target))
            ->where('documentable_id', $target->id)
            ->with(['gedDocument.folder', 'linker:id,name'])
            ->latest()
            ->get();

        return response()->json([
            'links' => $links->map(fn ($l) => $this->formatLink($l, $project)),
        ]);
    }

    // ── Picker AJAX : arborescence GED accessible ─────────────────────────

    /**
     * Retourne les dossiers + documents GED accessibles (≥ View) pour le picker.
     * Si folder_id est fourni, liste le contenu de ce dossier ; sinon les racines.
     */
    public function picker(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        /** @var User $user */
        $user = auth()->user();

        $folderId = $request->integer('folder_id', 0) ?: null;

        if ($folderId) {
            $parent = GedFolder::on('tenant')->findOrFail($folderId);
            $this->authorize('view', $parent);

            $folders = $parent->children()
                ->visibleFor($user)
                ->withCount(['children', 'documents'])
                ->orderBy('name')
                ->get();

            $documents = $parent->documents()
                ->whereNull('deleted_at')
                ->orderBy('name')
                ->get();
        } else {
            $folders = GedFolder::on('tenant')
                ->roots()
                ->visibleFor($user)
                ->withCount(['children', 'documents'])
                ->orderBy('name')
                ->get();

            $documents = GedDocument::on('tenant')
                ->whereNull('deleted_at')
                ->whereDoesntHave('folder')
                ->orderBy('name')
                ->get();
        }

        // Filtrer les documents avec au moins View
        $documents = $documents->filter(
            fn (GedDocument $doc) => $doc->folder
                ? $this->gedPerms->effectiveLevel($user, $doc->folder)->atLeast(GedPermissionLevel::View)
                : true
        )->values();

        return response()->json([
            'folder_id' => $folderId,
            'folders' => $folders->map(fn (GedFolder $f) => [
                'id' => $f->id,
                'name' => $f->name,
                'children_count' => $f->children_count,
                'documents_count' => $f->documents_count,
            ]),
            'documents' => $documents->map(fn (GedDocument $d) => [
                'id' => $d->id,
                'name' => $d->name,
                'mime_type' => $d->mime_type,
                'icon' => $d->icon(),
                'size' => $d->humanSize(),
            ]),
        ]);
    }

    // ── Lier un document GED ──────────────────────────────────────────────

    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorize('linkGed', $project);

        $validated = $request->validate([
            'ged_document_id' => 'required|integer|exists:tenant.ged_documents,id',
            'task_id' => 'nullable|integer|exists:tenant.tasks,id',
        ]);

        /** @var User $user */
        $user = auth()->user();

        $document = GedDocument::on('tenant')->with('folder')->findOrFail($validated['ged_document_id']);

        // Vérifier que l'utilisateur a au moins View sur le document GED
        if ($document->folder) {
            $level = $this->gedPerms->effectiveLevel($user, $document->folder);
            if (! $level->atLeast(GedPermissionLevel::View)) {
                abort(403, 'Accès au document GED insuffisant.');
            }
        }

        $target = $this->resolveTaskOrProject($project, $validated['task_id'] ?? null);

        // Éviter les doublons
        $exists = ProjectGedLink::on('tenant')
            ->where('documentable_type', get_class($target))
            ->where('documentable_id', $target->id)
            ->where('ged_document_id', $document->id)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Ce document est déjà lié.'], 422);
        }

        $link = ProjectGedLink::on('tenant')->create([
            'documentable_type' => get_class($target),
            'documentable_id' => $target->id,
            'ged_document_id' => $document->id,
            'linked_by' => $user->id,
        ]);

        $link->load(['gedDocument.folder', 'linker:id,name']);

        return response()->json([
            'success' => true,
            'link' => $this->formatLink($link, $project),
        ]);
    }

    // ── Délier un document GED ────────────────────────────────────────────

    public function destroy(Request $request, Project $project, ProjectGedLink $link): JsonResponse
    {
        $this->authorize('unlinkGed', $project);

        // Vérifier que le lien appartient bien à ce projet
        $this->assertBelongsToProject($link, $project);

        $link->delete();

        return response()->json(['success' => true]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function resolveTarget(Project $project, Request $request): Project|Task
    {
        return $this->resolveTaskOrProject($project, $request->integer('task_id') ?: null);
    }

    private function resolveTaskOrProject(Project $project, ?int $taskId): Project|Task
    {
        if ($taskId) {
            return Task::on('tenant')
                ->where('project_id', $project->id)
                ->findOrFail($taskId);
        }

        return $project;
    }

    private function assertBelongsToProject(ProjectGedLink $link, Project $project): void
    {
        $belongs = match ($link->documentable_type) {
            Project::class => $link->documentable_id === $project->id,
            Task::class => Task::on('tenant')
                ->where('id', $link->documentable_id)
                ->where('project_id', $project->id)
                ->exists(),
            default => false,
        };

        if (! $belongs) {
            abort(404);
        }
    }

    private function formatLink(ProjectGedLink $link, Project $project): array
    {
        $doc = $link->gedDocument;

        return [
            'id' => $link->id,
            'ged_document_id' => $link->ged_document_id,
            'documentable_type' => $link->documentable_type,
            'documentable_id' => $link->documentable_id,
            'document_name' => $doc?->name,
            'document_icon' => $doc?->icon(),
            'document_size' => $doc?->humanSize(),
            'document_mime' => $doc?->mime_type,
            'folder_name' => $doc?->folder?->name,
            'linked_by' => $link->linker->name ?? '—',
            'linked_at' => $link->created_at?->translatedFormat('d M Y'),
            'download_url' => $doc
                ? route('ged.documents.download', $doc->id)
                : null,
            'serve_url' => ($doc && $doc->isPreviewable())
                ? route('ged.documents.serve', $doc->id)
                : null,
        ];
    }
}
