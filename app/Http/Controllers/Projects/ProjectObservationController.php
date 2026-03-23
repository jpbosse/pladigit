<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Project;
use App\Models\Tenant\ProjectObservation;
use App\Services\AuditService;
use Illuminate\Http\Request;

/**
 * Observations des élus sur le tableau de bord projet.
 * Accès : tous les membres du projet peuvent lire.
 * Écriture : tous les membres (élus comme services).
 */
class ProjectObservationController extends Controller
{
    public function __construct(
        private readonly AuditService $audit,
    ) {}

    public function store(Request $request, Project $project)
    {
        $this->authorize('view', $project);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'type' => ['required', 'in:observation,question,validation,alerte'],
        ]);

        $observation = $project->observations()->create([
            ...$validated,
            'user_id' => auth()->id(),
        ]);

        $this->audit->log('project.observation.created', auth()->user(), [
            'project_id' => $project->id,
            'observation_id' => $observation->id,
            'type' => $observation->type,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'observation' => $observation->load('user'),
            ]);
        }

        return back()->with('success', 'Observation ajoutée.');
    }

    public function destroy(Request $request, Project $project, ProjectObservation $observation)
    {
        // Seul l'auteur ou un admin peut supprimer
        $user = auth()->user();
        abort_unless(
            $observation->user_id === $user->id
            || in_array($user->role, ['admin', 'president', 'dgs']),
            403
        );
        abort_if($observation->project_id !== $project->id, 404);

        $observation->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Observation supprimée.');
    }
}
