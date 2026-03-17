<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Project;
use App\Models\Tenant\Task;
use Illuminate\Http\Request;

/**
 * Contrôleur AJAX pour le Kanban Alpine.js.
 *
 * ADR-008 révisé : Livewire n'étant pas encore initialisé dans le projet,
 * le Kanban est implémenté en Alpine.js pur avec appels fetch() vers ces routes.
 *
 * TODO: migrer vers un composant Livewire en Phase 5 (GED) une fois
 *       le build pipeline Livewire validé dans vite.config.js.
 */
class KanbanController extends Controller
{
    /**
     * Déplace une tâche vers une nouvelle colonne (changement de status).
     * Met à jour status + sort_order.
     *
     * PATCH /projects/{project}/kanban/move
     * Body : { task_id, new_status, sort_order, ordered_ids[] }
     */
    public function move(Request $request, Project $project)
    {
        $validated = $request->validate([
            'task_id' => ['required', 'integer', 'exists:tenant.tasks,id'],
            'new_status' => ['required', 'in:todo,in_progress,in_review,done'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'ordered_ids' => ['sometimes', 'array'],
            'ordered_ids.*' => ['integer'],
        ]);

        $task = Task::on('tenant')->findOrFail($validated['task_id']);
        $this->authorize('move', $task);

        $oldStatus = $task->status;

        $task->update([
            'status' => $validated['new_status'],
            'sort_order' => $validated['sort_order'],
        ]);

        // Renuméroter les sort_order de la colonne destination
        if (! empty($validated['ordered_ids'])) {
            $this->reorderColumn($project, $validated['new_status'], $validated['ordered_ids']);
        }

        return response()->json([
            'success' => true,
            'task_id' => $task->id,
            'old_status' => $oldStatus,
            'new_status' => $validated['new_status'],
        ]);
    }

    /**
     * Réordonne les tâches dans une colonne sans changement de statut.
     *
     * PATCH /projects/{project}/kanban/reorder
     * Body : { status, ordered_ids[] }
     */
    public function reorder(Request $request, Project $project)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:todo,in_progress,in_review,done'],
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['integer'],
        ]);

        $this->authorize('view', $project);

        $this->reorderColumn($project, $validated['status'], $validated['ordered_ids']);

        return response()->json(['success' => true]);
    }

    /**
     * Renuméroter proprement les sort_order d'une colonne (repart de 0).
     *
     * @param  int[]  $orderedIds
     */
    private function reorderColumn(Project $project, string $status, array $orderedIds): void
    {
        foreach ($orderedIds as $position => $taskId) {
            Task::on('tenant')
                ->where('id', $taskId)
                ->where('project_id', $project->id)
                ->where('status', $status)
                ->update(['sort_order' => $position]);
        }
    }
}
