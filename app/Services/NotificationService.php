<?php

namespace App\Services;

use App\Models\Tenant\Event;
use App\Models\Tenant\Notification;
use App\Models\Tenant\Project;
use App\Models\Tenant\Task;
use App\Models\Tenant\User;

/**
 * Service de création des notifications in-app.
 *
 * Tous les types de notifications projets sont centralisés ici.
 * Chaque méthode crée les notifications pour les utilisateurs concernés.
 */
class NotificationService
{
    // ── Événements agenda projet ─────────────────────────────────────────────

    /**
     * Notifier les membres d'un projet qu'un événement a été créé.
     * Les événements "privé" ne notifient que le créateur (déjà au courant).
     */
    public function eventCreated(Event $event, User $creator): void
    {
        if ($event->visibility === 'private') {
            return;
        }

        /** @var \App\Models\Tenant\Project $project */
        $project = $event->project;
        if (! $project instanceof \App\Models\Tenant\Project) {
            return;
        }

        $recipients = $this->projectMembers($project, exclude: $creator->id);

        foreach ($recipients as $user) {
            Notification::on('tenant')->create([
                'user_id' => $user->id,
                'type' => 'agenda.event_created',
                'title' => '📅 Nouvel événement : '.$event->title,
                'body' => $this->eventBody($event),
                'link' => route('projects.show', $project).'?section=planif&view=agenda',
            ]);
        }
    }

    /**
     * Notifier les membres qu'un événement a été modifié.
     */
    public function eventUpdated(Event $event, User $updater): void
    {
        if ($event->visibility === 'private') {
            return;
        }

        /** @var \App\Models\Tenant\Project $project */
        $project = $event->project;
        if (! $project instanceof \App\Models\Tenant\Project) {
            return;
        }

        $recipients = $this->projectMembers($project, exclude: $updater->id);

        foreach ($recipients as $user) {
            Notification::on('tenant')->create([
                'user_id' => $user->id,
                'type' => 'agenda.event_updated',
                'title' => '📅 Événement modifié : '.$event->title,
                'body' => $this->eventBody($event),
                'link' => route('projects.show', $project).'?section=planif&view=agenda',
            ]);
        }
    }

    /**
     * Notifier les membres qu'un événement a été supprimé.
     */
    public function eventDeleted(string $title, Project $project, User $deleter): void
    {
        $recipients = $this->projectMembers($project, exclude: $deleter->id);

        foreach ($recipients as $user) {
            Notification::on('tenant')->create([
                'user_id' => $user->id,
                'type' => 'agenda.event_deleted',
                'title' => '📅 Événement annulé : '.$title,
                'body' => 'Cet événement a été supprimé du projet « '.$project->name.' ».',
                'link' => route('projects.show', $project).'?section=planif&view=agenda',
            ]);
        }
    }

    // ── Tâches projet ────────────────────────────────────────────────────────

    /**
     * Notifier un utilisateur qu'une tâche lui a été assignée.
     */
    public function taskAssigned(Task $task, User $assignee, User $assigner): void
    {
        if ($assignee->id === $assigner->id) {
            return;
        }

        $project = $task->project;

        Notification::on('tenant')->create([
            'user_id' => $assignee->id,
            'type' => 'project.task_assigned',
            'title' => '✅ Tâche assignée : '.$task->title,
            'body' => 'Assignée par '.$assigner->name.' dans « '.($project !== null ? $project->name : 'projet').' ».',
            'link' => $project ? route('projects.show', $project).'?section=planif' : null,
        ]);
    }

    /**
     * Notifier le créateur/propriétaire qu'une tâche a été complétée.
     */
    public function taskCompleted(Task $task, User $completedBy): void
    {
        $project = $task->project;
        if (! $project) {
            return;
        }

        // Notifier les owners du projet (sauf celui qui a complété)
        $owners = $project->projectMembers()
            ->with('user')
            ->get()
            ->filter(fn ($pm) => $pm->role === \App\Enums\ProjectRole::OWNER->value && $pm->user_id !== $completedBy->id)
            ->pluck('user');

        foreach ($owners as $owner) {
            Notification::on('tenant')->create([
                'user_id' => $owner->id,
                'type' => 'project.task_completed',
                'title' => '✅ Tâche terminée : '.$task->title,
                'body' => 'Complétée par '.$completedBy->name.' dans « '.$project->name.' ».',
                'link' => route('projects.show', $project).'?section=planif',
            ]);
        }
    }

    /**
     * Notifier les membres qu'un jalon a été atteint.
     */
    public function milestoneReached(string $milestoneTitle, Project $project, User $actor): void
    {
        $recipients = $this->projectMembers($project, exclude: $actor->id);

        foreach ($recipients as $user) {
            Notification::on('tenant')->create([
                'user_id' => $user->id,
                'type' => 'project.milestone_reached',
                'title' => '🏁 Jalon atteint : '.$milestoneTitle,
                'body' => 'Dans le projet « '.$project->name.' ».',
                'link' => route('projects.show', $project).'?section=but',
            ]);
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Membres du projet (avec leur User), en excluant un utilisateur.
     *
     * @return \Illuminate\Support\Collection<User>
     */
    private function projectMembers(Project $project, int $exclude = 0): \Illuminate\Support\Collection
    {
        return $project->projectMembers()
            ->with('user')
            ->get()
            ->filter(fn ($pm) => $pm->user_id !== $exclude && $pm->user !== null)
            ->pluck('user');
    }

    /**
     * Corps court d'un événement (date + lieu).
     */
    private function eventBody(Event $event): string
    {
        $starts = \Carbon\Carbon::parse($event->starts_at)->translatedFormat('d M Y à H:i');
        $body = $starts;

        if ($event->location) {
            $body .= ' — '.$event->location;
        }

        return $body;
    }
}
