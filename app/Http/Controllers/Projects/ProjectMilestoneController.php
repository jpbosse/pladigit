<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Project;
use App\Models\Tenant\ProjectMilestone;
use App\Models\Tenant\Task;
use App\Models\Tenant\User;
use App\Services\AuditService;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Gestion des nœuds hiérarchiques d'un projet (phases, étapes, jalons…).
 *
 * Un nœud est un ProjectMilestone avec un label libre (node_type) et un
 * parent_id optionnel. La profondeur maximale est de 4 niveaux (0–3).
 */
class ProjectMilestoneController extends Controller
{
    public function __construct(
        private readonly AuditService $audit,
    ) {}

    /**
     * Crée un nœud (racine ou enfant selon parent_id).
     */
    public function store(Request $request, Project $project)
    {
        $this->authorize('manageMilestones', $project);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'node_type' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:1000'],
            'parent_id' => ['nullable', 'integer', 'exists:tenant.project_milestones,id'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['required', 'date'],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'responsible_id' => ['nullable', 'integer', 'exists:tenant.users,id'],
            'department_id' => ['nullable', 'integer', 'exists:tenant.departments,id'],
        ]);

        if (! empty($validated['parent_id'])) {
            $parent = ProjectMilestone::on('tenant')->findOrFail($validated['parent_id']);
            abort_if($parent->project_id !== $project->id, 422, 'Parent invalide.');
            abort_if(
                $parent->depth() >= ProjectMilestone::MAX_DEPTH,
                422,
                'Profondeur maximale atteinte ('.(ProjectMilestone::MAX_DEPTH + 1).' niveaux).'
            );
        }

        $maxOrder = $project->milestones()
            ->where('parent_id', $validated['parent_id'] ?? null)
            ->max('sort_order') ?? 0;

        $defaultColor = empty($validated['parent_id']) ? '#1E3A5F' : '#EA580C';

        $milestone = $project->milestones()->create([
            ...$validated,
            'color' => $validated['color'] ?? $defaultColor,
            'sort_order' => $maxOrder + 10,
        ]);

        $this->audit->log('milestone.created', auth()->user(), [
            'new' => [
                'project_id' => $project->id,
                'milestone_id' => $milestone->id,
                'title' => $milestone->title,
                'node_type' => $milestone->node_type,
                'parent_id' => $milestone->parent_id,
            ],
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'milestone_id' => $milestone->id]);
        }

        return back()->with('success', ($milestone->node_type ?? 'Nœud').' créé.');
    }

    /**
     * Déplace un nœud vers le haut ou vers le bas parmi ses frères.
     */
    public function move(Request $request, Project $project, ProjectMilestone $milestone)
    {
        $this->authorize('manageMilestones', $project);

        $direction = $request->validate([
            'direction' => ['required', 'in:up,down'],
        ])['direction'];

        $siblings = $project->milestones()
            ->where('parent_id', $milestone->parent_id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        // Renumérote pour garantir des sort_order distincts
        foreach ($siblings as $i => $sib) {
            $sib->update(['sort_order' => ($i + 1) * 10]);
        }

        $siblings = $project->milestones()
            ->where('parent_id', $milestone->parent_id)
            ->orderBy('sort_order')
            ->get();

        $index = $siblings->search(fn ($s) => $s->id === $milestone->id);

        if ($index === false) {
            return back();
        }

        $swapIndex = $direction === 'up' ? $index - 1 : $index + 1;

        if ($swapIndex < 0 || $swapIndex >= $siblings->count()) {
            return back();
        }

        $current = $siblings[$index];
        $neighbor = $siblings[$swapIndex];

        $currentOrder = $current->sort_order;
        $neighborOrder = $neighbor->sort_order;

        $current->update(['sort_order' => $neighborOrder]);
        $neighbor->update(['sort_order' => $currentOrder]);

        return back()->with('success', 'Ordre mis à jour.');
    }

    public function update(Request $request, Project $project, ProjectMilestone $milestone)
    {
        $this->authorize('manageMilestones', $project);

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'node_type' => ['sometimes', 'nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:1000'],
            'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:tenant.project_milestones,id'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['sometimes', 'required', 'date'],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'reached' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'manual_progress' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'responsible_id' => ['sometimes', 'nullable', 'integer', 'exists:tenant.users,id'],
            'department_id' => ['sometimes', 'nullable', 'integer', 'exists:tenant.departments,id'],
        ]);

        // Reparentage : valider le nouveau parent si fourni
        if (array_key_exists('parent_id', $validated)) {
            $newParentId = $validated['parent_id'] ?: null;
            $validated['parent_id'] = $newParentId;

            if ($newParentId !== null) {
                $newParent = ProjectMilestone::on('tenant')->findOrFail($newParentId);
                abort_if($newParent->project_id !== $project->id, 422, 'Parent invalide.');
                abort_if($newParentId === $milestone->id, 422, 'Un nœud ne peut pas être son propre parent.');
                // Vérifier que le nouveau parent n'est pas un descendant du nœud
                $milestone->loadMissing(['children.children.children']);
                abort_if(
                    in_array($newParentId, array_slice($milestone->descendantIds(), 1), true),
                    422,
                    'Impossible de déplacer un nœud dans l\'un de ses descendants.'
                );
                abort_if(
                    $newParent->depth() >= ProjectMilestone::MAX_DEPTH,
                    422,
                    'Profondeur maximale atteinte ('.(ProjectMilestone::MAX_DEPTH + 1).' niveaux).'
                );
            }
        }

        // Vérifier que la nouvelle due_date ne coupe pas des tâches existantes
        if (isset($validated['due_date'])) {
            $newDueDate = Carbon::parse($validated['due_date']);

            // Charger tous les descendants pour agréger leurs tâches
            $milestone->loadMissing(['children.children.children']);
            $milestoneIds = $milestone->descendantIds();

            $latestTask = Task::on('tenant')
                ->whereIn('milestone_id', $milestoneIds)
                ->whereNotNull('due_date')
                ->whereNull('deleted_at')
                ->orderByDesc('due_date')
                ->first();

            if ($latestTask && $latestTask->due_date->gt($newDueDate)) {
                $errorMsg = sprintf(
                    'La date de fin ne peut pas être avancée au %s : la tâche « %s » est prévue le %s. Modifiez d\'abord cette tâche.',
                    $newDueDate->translatedFormat('d M Y'),
                    $latestTask->title,
                    $latestTask->due_date->translatedFormat('d M Y')
                );

                if ($request->wantsJson()) {
                    return response()->json(['success' => false, 'error' => $errorMsg], 422);
                }

                return back()->withErrors(['due_date' => $errorMsg])->withInput();
            }
        }

        // Marquer comme atteint
        if (isset($validated['reached'])) {
            if ($validated['reached'] && ! $milestone->isReached()) {
                // Guard : un nœud ne peut pas être marqué atteint si des enfants sont en cours
                $pending = $milestone->children()->whereNull('reached_at')->count();
                if ($pending > 0) {
                    $errorMsg = "Impossible de terminer ce nœud : {$pending} enfant".($pending > 1 ? 's' : '').' non atteint'.($pending > 1 ? 's' : '').'.';
                    if ($request->wantsJson()) {
                        return response()->json(['success' => false, 'error' => $errorMsg], 422);
                    }

                    return back()->withErrors(['reached' => $errorMsg]);
                }

                $milestone->markReached();
                $this->audit->log('milestone.reached', auth()->user(), [
                    'new' => [
                        'project_id' => $project->id,
                        'milestone_id' => $milestone->id,
                        'title' => $milestone->title,
                    ],
                ]);
            } elseif (! $validated['reached'] && $milestone->isReached()) {
                $milestone->update(['reached_at' => null]);
            }
        }

        if (isset($validated['reached']) && $validated['reached'] && $milestone->isReached()) {
            /** @var User $user */
            $user = auth()->user();
            app(NotificationService::class)->milestoneReached($milestone->title, $project, $user);
        }

        unset($validated['reached']);
        if (! empty($validated)) {
            $milestone->update($validated);
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Mis à jour.');
    }

    public function destroy(Project $project, ProjectMilestone $milestone)
    {
        $this->authorize('manageMilestones', $project);

        $label = $milestone->node_type ?? 'Nœud';
        $this->deleteRecursive($milestone);

        return back()->with('success', $label.' supprimé.');
    }

    /**
     * Supprime récursivement un nœud et tous ses descendants (soft delete).
     */
    private function deleteRecursive(ProjectMilestone $node): void
    {
        foreach ($node->children as $child) {
            $this->deleteRecursive($child);
        }
        $node->delete();
    }
}
