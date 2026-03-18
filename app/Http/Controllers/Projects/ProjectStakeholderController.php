<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Project;
use App\Models\Tenant\ProjectStakeholder;
use App\Services\AuditService;
use Illuminate\Http\Request;

class ProjectStakeholderController extends Controller
{
    public function __construct(
        private readonly AuditService $audit,
    ) {}

    public function store(Request $request, Project $project)
    {
        $this->authorize('manageStakeholders', $project);

        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:tenant.users,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'role' => ['required', 'string', 'max:255'],
            'adhesion' => ['required', 'in:champion,supporter,neutre,vigilant,resistant'],
            'influence' => ['required', 'in:high,medium,low'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        // Au moins user_id ou name requis
        if (empty($validated['user_id']) && empty($validated['name'])) {
            return back()->withErrors(['name' => 'Renseignez un nom ou sélectionnez un utilisateur.']);
        }

        $stakeholder = $project->stakeholders()->create($validated);

        $this->audit->log('project.stakeholder.created', auth()->user(), [
            'project_id' => $project->id,
            'stakeholder_id' => $stakeholder->id,
            'name' => $stakeholder->displayName(),
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'stakeholder' => $stakeholder->load('user')]);
        }

        return back()->with('success', 'Partie prenante ajoutée.');
    }

    public function update(Request $request, Project $project, ProjectStakeholder $stakeholder)
    {
        $this->authorize('manageStakeholders', $project);
        abort_if($stakeholder->project_id !== $project->id, 404);

        $validated = $request->validate([
            'role' => ['sometimes', 'required', 'string', 'max:255'],
            'adhesion' => ['sometimes', 'in:champion,supporter,neutre,vigilant,resistant'],
            'influence' => ['sometimes', 'in:high,medium,low'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $stakeholder->update($validated);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Partie prenante mise à jour.');
    }

    public function destroy(Request $request, Project $project, ProjectStakeholder $stakeholder)
    {
        $this->authorize('manageStakeholders', $project);
        abort_if($stakeholder->project_id !== $project->id, 404);

        $stakeholder->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Partie prenante supprimée.');
    }
}
