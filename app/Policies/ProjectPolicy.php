<?php

namespace App\Policies;

use App\Enums\ProjectRole;
use App\Enums\UserRole;
use App\Models\Tenant\Project;
use App\Models\Tenant\User;

/**
 * Politique d'accès aux projets.
 *
 * ADR-010 — deux couches cumulatives :
 *   1. before() : Admin/Président/DGS → retourne true (accès total)
 *   2. Méthodes spécifiques : ProjectRole local (owner/member/viewer)
 *
 * Pattern identique à MediaAlbumPolicy.
 *
 * Enregistrement dans AppServiceProvider :
 *   $this->policy(Project::class, ProjectPolicy::class);
 */
class ProjectPolicy
{
    /**
     * Admin, Président et DGS ont accès total à tous les projets du tenant.
     * Retourne null pour les autres rôles → passe aux méthodes spécifiques.
     */
    public function before(User $user, string $ability): ?bool
    {
        $role = $user->role ? UserRole::tryFrom($user->role) : null;

        if ($role && $role->atLeast(UserRole::DGS)) {
            return true;
        }

        return null;
    }

    /**
     * Peut voir le projet (liste et détail).
     * → Être membre du projet (les brouillons sont filtrés par scopeVisibleFor).
     */
    public function view(User $user, Project $project): bool
    {
        // Les brouillons ne sont visibles que par leur créateur
        if ($project->isDraft()) {
            return $project->created_by === $user->id;
        }

        return $project->isMember($user);
    }

    /**
     * Peut créer un projet.
     * → Resp. Direction et au-dessus.
     */
    public function create(User $user): bool
    {
        $role = $user->role ? UserRole::tryFrom($user->role) : null;

        return $role !== null && $role->atLeast(UserRole::RESP_DIRECTION);
    }

    /**
     * Peut modifier les paramètres du projet (nom, description, dates, couleur, statut).
     * → Owner du projet uniquement (Admin+ intercepté par before()).
     * → Pour un brouillon : seul le créateur.
     */
    public function update(User $user, Project $project): bool
    {
        // Brouillon : seul le créateur
        if ($project->isDraft()) {
            return $project->created_by === $user->id;
        }

        return $this->hasProjectRole($user, $project, ProjectRole::OWNER);
    }

    /**
     * Peut supprimer le projet.
     * → Owner uniquement.
     * → Pour un brouillon : seul le créateur.
     */
    public function delete(User $user, Project $project): bool
    {
        if ($project->isDraft()) {
            return $project->created_by === $user->id;
        }

        return $this->hasProjectRole($user, $project, ProjectRole::OWNER);
    }

    /**
     * Peut gérer les membres du projet (ajout, suppression, changement de rôle).
     * → Owner uniquement.
     */
    public function manageMembers(User $user, Project $project): bool
    {
        return $this->hasProjectRole($user, $project, ProjectRole::OWNER);
    }

    /**
     * Peut gérer les jalons (créer, modifier, marquer atteint, supprimer).
     * → Owner uniquement.
     */
    public function manageMilestones(User $user, Project $project): bool
    {
        return $this->hasProjectRole($user, $project, ProjectRole::OWNER);
    }

    /**
     * Peut créer des tâches dans ce projet.
     * → Owner ou Member.
     */
    public function createTask(User $user, Project $project): bool
    {
        $role = $project->memberRole($user);

        return $role !== null && $role->canEdit();
    }

    /**
     * Peut exporter l'agenda iCal du projet.
     * → Tout membre (owner, member, viewer).
     */
    public function exportIcal(User $user, Project $project): bool
    {
        return $project->isMember($user);
    }

    // ── Helper privé ──────────────────────────────────────────────────────

    /**
     * Vérifie qu'un utilisateur a exactement le rôle demandé dans le projet.
     */
    private function hasProjectRole(User $user, Project $project, ProjectRole $required): bool
    {
        $role = $project->memberRole($user);

        return $role === $required;
    }
}
