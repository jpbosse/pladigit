<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Project;
use App\Models\Tenant\ProjectCommAction;
use App\Models\Tenant\ProjectRisk;
use App\Services\AuditService;
use Illuminate\Http\Request;

/**
 * Conduite du changement : plan de communication + registre des risques.
 */
class ProjectChangeController extends Controller
{
    public function __construct(
        private readonly AuditService $audit,
    ) {}

    // ── Plan de communication ─────────────────────────────────────────────

    public function storeCommAction(Request $request, Project $project)
    {
        $this->authorize('manageChange', $project);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'target_audience' => ['required', 'string', 'max:255'],
            'channel' => ['required', 'in:email,reunion,affichage,courrier,intranet,autre'],
            'message' => ['nullable', 'string', 'max:5000'],
            'resources_needed' => ['nullable', 'string', 'max:2000'],
            'planned_at' => ['required', 'date'],
            'done_at' => ['nullable', 'date'],
            'responsible_id' => ['nullable', 'integer', 'exists:tenant.users,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $action = $project->commActions()->create($validated);

        $this->audit->log('project.comm_action.created', auth()->user(), [
            'project_id' => $project->id,
            'action_id' => $action->id,
            'title' => $action->title,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'action' => $action->load('responsible')]);
        }

        return back()->with('success', 'Action de communication ajoutée.');
    }

    public function updateCommAction(Request $request, Project $project, ProjectCommAction $action)
    {
        $this->authorize('manageChange', $project);
        abort_if($action->project_id !== $project->id, 404);

        // Normalise done_at="" (bouton "↩ À faire") en null
        if ($request->has('done_at') && $request->input('done_at') === '') {
            $request->merge(['done_at' => null]);
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'target_audience' => ['sometimes', 'required', 'string', 'max:255'],
            'channel' => ['sometimes', 'in:email,reunion,affichage,courrier,intranet,autre'],
            'message' => ['nullable', 'string', 'max:5000'],
            'resources_needed' => ['nullable', 'string', 'max:2000'],
            'planned_at' => ['sometimes', 'required', 'date'],
            'done_at' => ['nullable', 'date'],
            'responsible_id' => ['nullable', 'integer', 'exists:tenant.users,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $action->update($validated);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Action mise à jour.');
    }

    public function destroyCommAction(Request $request, Project $project, ProjectCommAction $action)
    {
        $this->authorize('manageChange', $project);
        abort_if($action->project_id !== $project->id, 404);

        $action->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Action supprimée.');
    }

    // ── Registre des risques ──────────────────────────────────────────────

    public function storeRisk(Request $request, Project $project)
    {
        $this->authorize('manageChange', $project);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['required', 'in:humain,technique,budget,planning,juridique,autre'],
            'probability' => ['required', 'in:low,medium,high'],
            'impact' => ['required', 'in:low,medium,high,critical'],
            'status' => ['sometimes', 'in:identified,monitored,mitigated,closed'],
            'mitigation_plan' => ['nullable', 'string', 'max:5000'],
            'owner_id' => ['nullable', 'integer', 'exists:tenant.users,id'],
        ]);

        $risk = $project->risks()->create($validated);

        $this->audit->log('project.risk.created', auth()->user(), [
            'project_id' => $project->id,
            'risk_id' => $risk->id,
            'title' => $risk->title,
            'score' => $risk->score(),
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'risk' => $risk->load('owner')]);
        }

        return back()->with('success', 'Risque ajouté au registre.');
    }

    public function updateRisk(Request $request, Project $project, ProjectRisk $risk)
    {
        $this->authorize('manageChange', $project);
        abort_if($risk->project_id !== $project->id, 404);

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['sometimes', 'in:humain,technique,budget,planning,juridique,autre'],
            'probability' => ['sometimes', 'in:low,medium,high'],
            'impact' => ['sometimes', 'in:low,medium,high,critical'],
            'status' => ['sometimes', 'in:identified,monitored,mitigated,closed'],
            'mitigation_plan' => ['nullable', 'string', 'max:5000'],
            'owner_id' => ['nullable', 'integer', 'exists:tenant.users,id'],
        ]);

        $risk->update($validated);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Risque mis à jour.');
    }

    public function destroyRisk(Request $request, Project $project, ProjectRisk $risk)
    {
        $this->authorize('manageChange', $project);
        abort_if($risk->project_id !== $project->id, 404);

        $risk->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Risque supprimé.');
    }
}
