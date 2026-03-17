<?php

namespace App\Policies;

use App\Enums\ProjectRole;
use App\Enums\UserRole;
use App\Models\Tenant\Task;
use App\Models\Tenant\User;

/**
 * Politique d'accès aux tâches.
 *
 * Les droits sur une tâche dépendent du ProjectRole de l'utilisateur
 * dans le projet parent, sauf pour Admin/Président/DGS (before()).
 */
class TaskPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        $role = $user->role ? UserRole::tryFrom($user->role) : null;

        if ($role && $role->atLeast(UserRole::DGS)) {
            return true;
        }

        return null;
    }

    /**
     * Peut voir la tâche → être membre du projet.
     */
    public function view(User $user, Task $task): bool
    {
        return $task->project->isMember($user);
    }

    /**
     * Peut créer une tâche → owner ou member.
     */
    public function create(User $user, Task $task): bool
    {
        $role = $task->project->memberRole($user);

        return $role !== null && $role->canEdit();
    }

    /**
     * Peut modifier une tâche :
     *  - owner du projet → toutes les tâches
     *  - member          → seulement ses propres tâches (assigned_to ou created_by)
     *  - viewer          → jamais
     */
    public function update(User $user, Task $task): bool
    {
        $projectRole = $task->project->memberRole($user);

        if ($projectRole === null) {
            return false;
        }

        if ($projectRole === ProjectRole::OWNER) {
            return true;
        }

        if ($projectRole === ProjectRole::MEMBER) {
            return $task->assigned_to === $user->id
                || $task->created_by === $user->id;
        }

        return false;
    }

    /**
     * Peut déplacer une tâche dans le Kanban (move/reorder).
     * → owner ou member.
     */
    public function move(User $user, Task $task): bool
    {
        $role = $task->project->memberRole($user);

        return $role !== null && $role->canEdit();
    }

    /**
     * Peut modifier les dates de la tâche dans le Gantt.
     * → owner ou member (comme move).
     */
    public function updateDates(User $user, Task $task): bool
    {
        return $this->move($user, $task);
    }

    /**
     * Peut supprimer une tâche.
     * → owner du projet uniquement, ou auteur de la tâche qui est member.
     */
    public function delete(User $user, Task $task): bool
    {
        $projectRole = $task->project->memberRole($user);

        if ($projectRole === ProjectRole::OWNER) {
            return true;
        }

        if ($projectRole === ProjectRole::MEMBER) {
            return $task->created_by === $user->id;
        }

        return false;
    }

    /**
     * Peut commenter → owner ou member.
     */
    public function comment(User $user, Task $task): bool
    {
        $role = $task->project->memberRole($user);

        return $role !== null && $role->canEdit();
    }

    /**
     * Peut gérer les dépendances (ajouter/supprimer).
     * → owner ou member.
     */
    public function manageDependencies(User $user, Task $task): bool
    {
        return $this->move($user, $task);
    }
}
