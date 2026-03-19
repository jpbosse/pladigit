<?php

namespace App\Policies;

use App\Enums\ProjectRole;
use App\Enums\UserRole;
use App\Models\Tenant\Project;
use App\Models\Tenant\Task;
use App\Models\Tenant\User;

/**
 * Politique d'accès aux projets — ADR-010 + ADR-011.
 *
 * Trois couches cumulatives :
 *
 *   1. UserRole global (before) :
 *      Admin / Président / DGS → accès total à tous les projets
 *
 *   2. Hiérarchie organisationnelle (before, ADR-011) :
 *      Resp. Direction → accès en lecture à tous les projets de sa direction et ses services
 *      Resp. Service   → accès en lecture à tous les projets de son service
 *      (même logique que AlbumPermissionService pour la Photothèque)
 *
 *   3. ProjectRole local :
 *      Owner / Member / Viewer → droits d'écriture selon le rôle dans le projet
 */
class ProjectPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        $role = $user->role ? UserRole::tryFrom($user->role) : null;

        // Couche 1 uniquement : Admin / Président / DGS → accès total
        // La couche hiérarchique (ADR-011) est gérée directement dans view()
        // afin de pouvoir vérifier le périmètre ET le flag is_private.
        if ($role && $role->atLeast(UserRole::DGS)) {
            return true;
        }

        return null;
    }

    public function view(User $user, Project $project): bool
    {
        if ($project->isDraft()) {
            return $project->created_by === $user->id;
        }

        // Membre explicite → toujours autorisé (privé ou non)
        if ($project->isMember($user)) {
            return true;
        }

        // Projet privé → membres explicites uniquement, hiérarchie bloquée
        if ($project->is_private) {
            return false;
        }

        // Couche hiérarchique (ADR-011) : Resp. Direction / Resp. Service
        // → peuvent voir les projets NON PRIVÉS dont un membre est dans leur périmètre
        $role = $user->role ? UserRole::tryFrom($user->role) : null;
        if ($role && $role->atLeast(UserRole::RESP_SERVICE)) {
            $visibleUserIds = $user->visibleUsers()->pluck('id');

            return $project->projectMembers()
                ->whereIn('user_id', $visibleUserIds)
                ->exists();
        }

        return false;
    }

    public function create(User $user): bool
    {
        $role = $user->role ? UserRole::tryFrom($user->role) : null;

        return $role !== null && $role->atLeast(UserRole::RESP_DIRECTION);
    }

    public function update(User $user, Project $project): bool
    {
        if ($project->isDraft()) {
            return $project->created_by === $user->id;
        }

        return $this->hasProjectRole($user, $project, ProjectRole::OWNER);
    }

    public function delete(User $user, Project $project): bool
    {
        if ($project->isDraft()) {
            return $project->created_by === $user->id;
        }

        return $this->hasProjectRole($user, $project, ProjectRole::OWNER);
    }

    public function manageMembers(User $user, Project $project): bool
    {
        return $this->hasProjectRole($user, $project, ProjectRole::OWNER);
    }

    public function manageMilestones(User $user, Project $project): bool
    {
        return $this->hasProjectRole($user, $project, ProjectRole::OWNER);
    }

    /**
     * Budget : owner uniquement — engagement financier.
     */
    public function manageBudget(User $user, Project $project): bool
    {
        return $this->hasProjectRole($user, $project, ProjectRole::OWNER);
    }

    /**
     * Parties prenantes : owner uniquement — vision stratégique.
     */
    public function manageStakeholders(User $user, Project $project): bool
    {
        return $this->hasProjectRole($user, $project, ProjectRole::OWNER);
    }

    /**
     * Conduite du changement : owner ET member.
     */
    public function manageChange(User $user, Project $project): bool
    {
        $role = $project->memberRole($user);

        return $role !== null && $role->canEdit();
    }

    public function createTask(User $user, Project $project): bool
    {
        $role = $project->memberRole($user);

        return $role !== null && $role->canEdit();
    }

    /**
     * Déplacement Kanban : owner ou member.
     */
    public function move(User $user, Task $task): bool
    {
        $role = $task->project?->memberRole($user);

        return $role !== null && $role->canEdit();
    }

    public function exportIcal(User $user, Project $project): bool
    {
        return $project->isMember($user);
    }

    private function hasProjectRole(User $user, Project $project, ProjectRole $required): bool
    {
        return $project->memberRole($user) === $required;
    }
}
