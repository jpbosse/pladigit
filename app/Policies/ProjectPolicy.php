<?php

namespace App\Policies;

use App\Enums\ProjectRole;
use App\Enums\UserRole;
use App\Models\Tenant\Project;
use App\Models\Tenant\Task;
use App\Models\Tenant\User;

/**
 * Politique d'accès aux projets — ADR-010.
 *
 * before() : Admin / Président / DGS → accès total (retourne true)
 * Méthodes : ProjectRole local (owner / member / viewer)
 */
class ProjectPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        $role = $user->role ? UserRole::tryFrom($user->role) : null;
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

        return $project->isMember($user);
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
     * Budget : owner uniquement — engagement financier = responsabilité du chef de projet.
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
     * Conduite du changement (comm + risques) : owner ET member.
     * Tout contributeur peut identifier un risque ou planifier une action.
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
