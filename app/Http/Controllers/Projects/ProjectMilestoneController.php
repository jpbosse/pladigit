<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Project;
use App\Models\Tenant\ProjectMilestone;
use App\Services\AuditService;
use Illuminate\Http\Request;

/**
 * Gestion des jalons (milestones) d'un projet.
 */
class ProjectMilestoneController extends Controller
{
    public function __construct(
        private readonly AuditService $audit,
    ) {}

    public function store(Request $request, Project $project)
    {
        $this->authorize('manageMilestones', $project);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'due_date' => ['required', 'date'],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $milestone = $project->milestones()->create([
            ...$validated,
            'color' => $validated['color'] ?? '#EA580C',
        ]);

        $this->audit->log('milestone.created', auth()->user(), [
            'project_id' => $project->id,
            'milestone_id' => $milestone->id,
            'milestone_name' => $milestone->title,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'milestone_id' => $milestone->id]);
        }

        return back()->with('success', 'Jalon créé.');
    }

    public function update(Request $request, Project $project, ProjectMilestone $milestone)
    {
        $this->authorize('manageMilestones', $project);

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'due_date' => ['sometimes', 'required', 'date'],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'reached' => ['sometimes', 'boolean'],
        ]);

        // Marquer comme atteint
        if (isset($validated['reached']) && $validated['reached'] && ! $milestone->isReached()) {
            $milestone->markReached();
            $this->audit->log('milestone.reached', auth()->user(), [
                'project_id' => $project->id,
                'milestone_id' => $milestone->id,
                'milestone_name' => $milestone->title,
            ]);
        }

        unset($validated['reached']);
        if (! empty($validated)) {
            $milestone->update($validated);
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Jalon mis à jour.');
    }

    public function destroy(Project $project, ProjectMilestone $milestone)
    {
        $this->authorize('manageMilestones', $project);

        $milestone->delete();

        return back()->with('success', 'Jalon supprimé.');
    }
}
