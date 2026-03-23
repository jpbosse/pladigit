<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Project;
use App\Models\Tenant\ProjectDocument;
use App\Models\Tenant\ProjectMilestone;
use App\Models\Tenant\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProjectDocumentController extends Controller
{
    // ── Résolution du documentable ─────────────────────────────────────────

    /**
     * Résout le modèle cible depuis les paramètres de route.
     * Priorité : milestone > task > project.
     *
     * @return array{Project, Project|Task|ProjectMilestone}
     */
    private function resolveTarget(Project $project, Request $request): array
    {
        if ($request->filled('milestone_id')) {
            $milestone = ProjectMilestone::on('tenant')
                ->where('project_id', $project->id)
                ->findOrFail($request->integer('milestone_id'));

            return [$project, $milestone];
        }

        if ($request->filled('task_id')) {
            $task = Task::on('tenant')
                ->where('project_id', $project->id)
                ->findOrFail($request->integer('task_id'));

            return [$project, $task];
        }

        return [$project, $project];
    }

    // ── Upload fichier ─────────────────────────────────────────────────────

    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        [$project, $target] = $this->resolveTarget($project, $request);

        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                'max:'.(ProjectDocument::maxSizeMb() * 1024),
                'mimes:'.implode(',', ProjectDocument::allowedExtensions()),
            ],
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        $file = $request->file('file');
        $origName = $file->getClientOriginalName();
        $slug = app(\App\Services\TenantManager::class)->current()->slug ?? 'tenant';
        $dir = "project-docs/{$slug}/{$project->id}";
        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs($dir, $filename, ['disk' => 'private']);

        /** @var \App\Models\Tenant\User $user */
        $user = auth()->user();

        $doc = ProjectDocument::on('tenant')->create([
            'documentable_type' => get_class($target),
            'documentable_id' => $target->id,
            'type' => 'file',
            'driver' => 'local',
            'name' => $validated['name'] ?? $origName,
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'description' => $validated['description'] ?? null,
            'uploaded_by' => $user->id,
        ]);

        $doc->load('uploader:id,name');

        return response()->json([
            'success' => true,
            'document' => $this->formatDoc($doc),
        ]);
    }

    // ── Ajout lien URL ─────────────────────────────────────────────────────

    public function storeLink(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        [$project, $target] = $this->resolveTarget($project, $request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'path' => 'required|url|max:1000',
            'description' => 'nullable|string|max:500',
        ]);

        /** @var \App\Models\Tenant\User $user */
        $user = auth()->user();

        $doc = ProjectDocument::on('tenant')->create([
            'documentable_type' => get_class($target),
            'documentable_id' => $target->id,
            'type' => 'link',
            'driver' => 'local',
            'name' => $validated['name'],
            'path' => $validated['path'],
            'mime_type' => null,
            'size_bytes' => 0,
            'description' => $validated['description'] ?? null,
            'uploaded_by' => $user->id,
        ]);

        $doc->load('uploader:id,name');

        return response()->json([
            'success' => true,
            'document' => $this->formatDoc($doc),
        ]);
    }

    // ── Téléchargement ─────────────────────────────────────────────────────

    public function download(Project $project, ProjectDocument $document): mixed
    {
        $this->authorize('view', $project);

        if ($document->type === 'link') {
            return redirect($document->path);
        }

        if (! Storage::disk('private')->exists($document->path)) {
            abort(404, 'Fichier introuvable.');
        }

        return Storage::disk('private')->download($document->path, $document->name);
    }

    // ── Suppression ────────────────────────────────────────────────────────

    public function destroy(Project $project, ProjectDocument $document): JsonResponse
    {
        $this->authorize('update', $project);

        if ($document->type === 'file' && $document->driver === 'local') {
            Storage::disk('private')->delete($document->path);
        }

        $document->delete();

        return response()->json(['success' => true]);
    }

    // ── Liste AJAX ─────────────────────────────────────────────────────────

    public function index(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        [$project, $target] = $this->resolveTarget($project, $request);

        $docs = ProjectDocument::on('tenant')
            ->where('documentable_type', get_class($target))
            ->where('documentable_id', $target->id)
            ->with('uploader:id,name')
            ->latest()
            ->get();

        return response()->json([
            'documents' => $docs->map(fn ($d) => $this->formatDoc($d)),
        ]);
    }

    // ── Format JSON ────────────────────────────────────────────────────────

    private function formatDoc(ProjectDocument $doc): array
    {
        // Récupérer le project_id selon le type de documentable
        $projectId = match ($doc->documentable_type) {
            'App\\Models\\Tenant\\Project' => $doc->documentable_id,
            'App\\Models\\Tenant\\Task' => $doc->documentable instanceof Task
                ? $doc->documentable->project_id
                : $doc->documentable_id,
            'App\\Models\\Tenant\\ProjectMilestone' => $doc->documentable instanceof ProjectMilestone
                ? $doc->documentable->project_id
                : $doc->documentable_id,
            default => $doc->documentable_id,
        };

        return [
            'id' => $doc->id,
            'type' => $doc->type,
            'icon' => $doc->icon(),
            'name' => $doc->name,
            'description' => $doc->description,
            'size' => $doc->type === 'file' ? $doc->humanSize() : null,
            'mime_type' => $doc->mime_type,
            'uploader' => $doc->uploader->name ?? '—',
            'created_at' => $doc->created_at?->translatedFormat('d M Y'),
            'download_url' => $projectId
                ? route('projects.documents.download', [$projectId, $doc->id])
                : '#',
        ];
    }
}
