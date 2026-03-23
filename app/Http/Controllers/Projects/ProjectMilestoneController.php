<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Project;
use App\Models\Tenant\ProjectMilestone;
use App\Services\AuditService;
use Illuminate\Http\Request;

/**
 * Gestion des jalons et phases d'un projet.
 *
 * Phase  : parent_id = null — créée via POST /projects/{project}/phases
 * Jalon  : parent_id = id phase — créé via POST /projects/{project}/milestones
 */
class ProjectMilestoneController extends Controller
{
    public function __construct(
        private readonly AuditService $audit,
    ) {}

    /**
     * Crée une Phase (jalon de niveau supérieur, sans parent).
     */
    public function storePhase(Request $request, Project $project)
    {
        $this->authorize('manageMilestones', $project);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:start_date'],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        // sort_order = après la dernière phase existante
        $maxOrder = $project->milestones()->whereNull('parent_id')->max('sort_order') ?? 0;

        $phase = $project->milestones()->create([
            ...$validated,
            'parent_id' => null,
            'color' => $validated['color'] ?? '#1E3A5F',
            'sort_order' => $maxOrder + 10,
        ]);

        $this->audit->log('phase.created', auth()->user(), ['new' => ['project_id' => $project->id, 'phase_id' => $phase->id, 'phase_name' => $phase->title]]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'milestone_id' => $phase->id]);
        }

        return back()->with('success', 'Phase créée.');
    }

    /**
     * Crée un Jalon (enfant d'une phase, ou autonome si parent_id absent).
     */
    public function store(Request $request, Project $project)
    {
        $this->authorize('manageMilestones', $project);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'parent_id' => ['nullable', 'integer', 'exists:tenant.project_milestones,id'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['required', 'date'],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        // Vérifier que le parent appartient bien à ce projet
        if (! empty($validated['parent_id'])) {
            $parent = ProjectMilestone::on('tenant')->findOrFail($validated['parent_id']);
            abort_if($parent->project_id !== $project->id, 422, 'Phase invalide.');
            abort_if($parent->parent_id !== null, 422, 'Impossible d\'imbriquer un jalon dans un autre jalon.');
        }

        $maxOrder = $project->milestones()
            ->where('parent_id', $validated['parent_id'] ?? null)
            ->max('sort_order') ?? 0;

        $milestone = $project->milestones()->create([
            ...$validated,
            'color' => $validated['color'] ?? '#EA580C',
            'sort_order' => $maxOrder + 10,
        ]);

        $this->audit->log('milestone.created', auth()->user(), ['new' => ['project_id' => $project->id, 'milestone_id' => $milestone->id, 'milestone_name' => $milestone->title]]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'milestone_id' => $milestone->id]);
        }

        return back()->with('success', 'Jalon créé.');
    }

    /**
     * Déplace une phase vers le haut ou vers le bas.
     * Renumérote tous les sort_order avant d'échanger pour garantir un ordre stable.
     */
    public function move(Request $request, Project $project, ProjectMilestone $milestone)
    {
        $this->authorize('manageMilestones', $project);

        $direction = $request->validate([
            'direction' => ['required', 'in:up,down'],
        ])['direction'];

        // Toutes les phases du projet, dans l'ordre actuel
        $phases = $project->milestones()
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        // Renumérote d'abord 10, 20, 30... pour garantir des sort_order distincts
        foreach ($phases as $i => $phase) {
            $phase->update(['sort_order' => ($i + 1) * 10]);
        }

        // Recharge après numérotation
        $phases = $project->milestones()
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get();

        $index = $phases->search(fn ($p) => $p->id === $milestone->id);

        if ($index === false) {
            return back();
        }

        $swapIndex = $direction === 'up' ? $index - 1 : $index + 1;

        if ($swapIndex < 0 || $swapIndex >= $phases->count()) {
            return back();
        }

        // Échanger les sort_order des deux phases
        $current = $phases[$index];
        $neighbor = $phases[$swapIndex];

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
            'description' => ['nullable', 'string', 'max:1000'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['sometimes', 'required', 'date'],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'reached' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        // Vérifier que la nouvelle due_date ne coupe pas des tâches existantes
        if (isset($validated['due_date'])) {
            $newDueDate = \Carbon\Carbon::parse($validated['due_date']);

            // Récupérer les jalons concernés (ce jalon + ses enfants si c'est une phase)
            $milestoneIds = [$milestone->id];
            if ($milestone->isPhase()) {
                $milestoneIds = array_merge(
                    $milestoneIds,
                    $milestone->children()->pluck('id')->toArray()
                );
            }

            // Trouver la tâche la plus tardive rattachée à ces jalons
            $latestTask = \App\Models\Tenant\Task::on('tenant')
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
                $milestone->markReached();
                $this->audit->log('milestone.reached', auth()->user(), ['new' => ['project_id' => $project->id, 'milestone_id' => $milestone->id, 'milestone_name' => $milestone->title]]);
            } elseif (! $validated['reached'] && $milestone->isReached()) {
                // Annuler l'atteinte
                $milestone->update(['reached_at' => null]);
            }
        }
        if (isset($validated['reached']) && $validated['reached'] && $milestone->isReached()) {
            // Notifier les membres du projet (seulement lors de l'atteinte initiale)
            /** @var \App\Models\Tenant\User $user */
            $user = auth()->user();
            app(\App\Services\NotificationService::class)->milestoneReached($milestone->title, $project, $user);
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

        // Supprimer les jalons enfants d'abord (soft delete cascade)
        if ($milestone->isPhase()) {
            $milestone->children()->each(fn ($child) => $child->delete());
        }

        $milestone->delete();

        return back()->with('success', $milestone->isPhase() ? 'Phase supprimée.' : 'Jalon supprimé.');
    }
}
