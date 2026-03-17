<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Event;
use App\Models\Tenant\Project;
use App\Models\Tenant\User;
use Illuminate\Http\Request;

/**
 * Création d'événements liés à un projet (onglet Agenda).
 */
class ProjectEventController extends Controller
{
    public function store(Request $request, Project $project)
    {
        // Tout membre peut créer un événement dans le projet
        $this->authorize('view', $project);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'location' => ['nullable', 'string', 'max:500'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'all_day' => ['boolean'],
            'visibility' => ['required', 'in:private,restricted,public'],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        /** @var User $user */
        $user = auth()->user();

        $event = Event::create([
            ...$validated,
            'created_by' => $user->id,
            'project_id' => $project->id,
            'color' => $validated['color'] ?? $project->color,
            'all_day' => $validated['all_day'] ?? false,
        ]);

        // Le créateur est automatiquement ajouté comme participant accepté
        $event->participants()->create([
            'user_id' => $user->id,
            'status' => 'accepted',
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'event_id' => $event->id]);
        }

        return back()->with('success', 'Événement créé.');
    }
}
