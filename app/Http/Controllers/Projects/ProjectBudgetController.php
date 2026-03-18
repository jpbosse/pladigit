<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Project;
use App\Models\Tenant\ProjectBudget;
use App\Services\AuditService;
use Illuminate\Http\Request;

/**
 * Gestion des enveloppes budgétaires d'un projet.
 */
class ProjectBudgetController extends Controller
{
    public function __construct(
        private readonly AuditService $audit,
    ) {}

    public function store(Request $request, Project $project)
    {
        $this->authorize('manageBudget', $project);

        $validated = $request->validate([
            'type' => ['required', 'in:invest,fonct'],
            'label' => ['required', 'string', 'max:255'],
            'year' => ['required', 'integer', 'min:2000', 'max:2099'],
            'amount_planned' => ['required', 'numeric', 'min:0'],
            'amount_committed' => ['nullable', 'numeric', 'min:0'],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
            'cofinancer' => ['nullable', 'string', 'max:255'],
            'cofinancing_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $budget = $project->budgets()->create([
            ...$validated,
            'amount_committed' => $validated['amount_committed'] ?? 0,
            'amount_paid' => $validated['amount_paid'] ?? 0,
            'created_by' => auth()->id(),
        ]);

        $this->audit->log('project.budget.created', auth()->user(), [
            'project_id' => $project->id,
            'budget_id' => $budget->id,
            'label' => $budget->label,
            'type' => $budget->type,
            'planned' => $budget->amount_planned,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'budget' => $budget]);
        }

        return back()->with('success', 'Ligne budgétaire ajoutée.');
    }

    public function update(Request $request, Project $project, ProjectBudget $budget)
    {
        $this->authorize('manageBudget', $project);
        abort_if($budget->project_id !== $project->id, 404);

        $validated = $request->validate([
            'type' => ['sometimes', 'in:invest,fonct'],
            'label' => ['sometimes', 'required', 'string', 'max:255'],
            'year' => ['sometimes', 'integer', 'min:2000', 'max:2099'],
            'amount_planned' => ['sometimes', 'numeric', 'min:0'],
            'amount_committed' => ['sometimes', 'numeric', 'min:0'],
            'amount_paid' => ['sometimes', 'numeric', 'min:0'],
            'cofinancer' => ['nullable', 'string', 'max:255'],
            'cofinancing_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $budget->update($validated);

        $this->audit->log('project.budget.updated', auth()->user(), [
            'project_id' => $project->id,
            'budget_id' => $budget->id,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'budget' => $budget->fresh()]);
        }

        return back()->with('success', 'Ligne budgétaire mise à jour.');
    }

    public function destroy(Request $request, Project $project, ProjectBudget $budget)
    {
        $this->authorize('manageBudget', $project);
        abort_if($budget->project_id !== $project->id, 404);

        $budget->delete();

        $this->audit->log('project.budget.deleted', auth()->user(), [
            'project_id' => $project->id,
            'budget_id' => $budget->id,
            'label' => $budget->label,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Ligne budgétaire supprimée.');
    }
}
