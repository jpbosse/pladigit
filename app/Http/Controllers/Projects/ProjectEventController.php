<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Event;
use App\Models\Tenant\Project;
use App\Models\Tenant\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;

/**
 * Gestion des événements liés à un projet (onglet Agenda).
 *
 * Droits :
 *   - store  : tout membre du projet
 *   - update : créateur de l'événement ou owner du projet
 *   - destroy: créateur de l'événement ou owner du projet
 */
class ProjectEventController extends Controller
{
    public function store(Request $request, Project $project)
    {
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
            'visio_url' => ['nullable', 'url', 'max:500'],
        ]);

        /** @var User $user */
        $user = auth()->user();

        $event = Event::on('tenant')->create([
            ...$validated,
            'created_by' => $user->id,
            'project_id' => $project->id,
            'color' => $validated['color'] ?? $project->color,
            'all_day' => $validated['all_day'] ?? false,
        ]);

        $event->participants()->create([
            'user_id' => $user->id,
            'status' => 'accepted',
        ]);

        // Notifier les membres du projet
        app(NotificationService::class)->eventCreated($event, $user);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'event_id' => $event->id]);
        }

        return back()->with('success', 'Événement créé.');
    }

    public function update(Request $request, Project $project, Event $event)
    {
        $this->authorize('view', $project);
        $this->authorizeEventAction($event);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'location' => ['nullable', 'string', 'max:500'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'all_day' => ['boolean'],
            'visibility' => ['required', 'in:private,restricted,public'],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'visio_url' => ['nullable', 'url', 'max:500'],
        ]);

        $event->update($validated);

        /** @var User $user */
        $user = auth()->user();
        app(NotificationService::class)->eventUpdated($event, $user);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Événement mis à jour.');
    }

    public function destroy(Project $project, Event $event)
    {
        $this->authorize('view', $project);
        $this->authorizeEventAction($event);

        $title = $event->title;

        /** @var User $user */
        $user = auth()->user();

        $event->delete();

        app(NotificationService::class)->eventDeleted($title, $project, $user);

        return back()->with('success', 'Événement supprimé.');
    }

    /**
     * Seul le créateur de l'événement ou un owner/DGS du projet peut modifier/supprimer.
     */
    private function authorizeEventAction(Event $event): void
    {
        /** @var User $user */
        $user = auth()->user();
        $role = $user->role ? \App\Enums\UserRole::tryFrom($user->role) : null;
        $isAdmin = $role && $role->atLeast(\App\Enums\UserRole::DGS);

        $project = $event->project;
        $isOwner = $project && $project->memberRole($user) === \App\Enums\ProjectRole::OWNER;
        $isCreator = $event->created_by === $user->id;

        abort_unless($isCreator || $isOwner || $isAdmin, 403, 'Vous ne pouvez pas modifier cet événement.');
    }
}
