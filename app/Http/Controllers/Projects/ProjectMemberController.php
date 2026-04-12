<?php

namespace App\Http\Controllers\Projects;

use App\Enums\ProjectRole;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Project;
use App\Models\Tenant\ProjectCommAction;
use App\Models\Tenant\ProjectMember;
use App\Models\Tenant\Task;
use App\Models\Tenant\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Gestion des membres d'un projet.
 * Seul l'owner peut ajouter/retirer des membres.
 * Le dernier owner ne peut jamais être retiré.
 */
class ProjectMemberController extends Controller
{
    public function __construct(
        private readonly AuditService $audit,
    ) {}

    public function store(Request $request, Project $project)
    {
        $this->authorize('manageMembers', $project);

        $validated = $request->validate([
            'user_id' => ['required', 'exists:tenant.users,id'],
            'role' => ['required', ProjectRole::rule()],
        ]);

        // Éviter les doublons — mise à jour du rôle si déjà membre
        $existing = ProjectMember::on('tenant')
            ->where('project_id', $project->id)
            ->where('user_id', $validated['user_id'])
            ->first();

        if ($existing) {
            $existing->update(['role' => $validated['role']]);
        } else {
            ProjectMember::create([
                'project_id' => $project->id,
                'user_id' => $validated['user_id'],
                'role' => $validated['role'],
            ]);
        }

        $member = User::on('tenant')->find($validated['user_id']);

        $this->audit->log('project.member.added', auth()->user(), ['new' => ['project_id' => $project->id, 'user_id' => $validated['user_id'], 'user_name' => $member?->name, 'role' => $validated['role']]]);

        return back()->with('success', 'Membre ajouté au projet.');
    }

    public function destroy(Project $project, User $user)
    {
        $this->authorize('manageMembers', $project);

        // Protéger le dernier owner
        $ownerCount = ProjectMember::on('tenant')
            ->where('project_id', $project->id)
            ->where('role', ProjectRole::OWNER->value)
            ->count();

        $memberToRemove = ProjectMember::on('tenant')
            ->where('project_id', $project->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        if ($memberToRemove->role === ProjectRole::OWNER->value && $ownerCount <= 1) {
            return back()->withErrors(['member' => 'Impossible de retirer le dernier chef de projet.']);
        }

        $memberToRemove->delete();

        $this->audit->log('project.member.removed', auth()->user(), ['new' => ['project_id' => $project->id, 'user_id' => $user->id, 'user_name' => $user->name]]);

        return back()->with('success', 'Membre retiré du projet.');
    }

    /**
     * Réaffecte toutes les affectations d'un utilisateur vers un autre
     * dans ce projet (membership, tâches, responsabilités comm).
     *
     * Cas d'usage : membre supprimé puis recréé avec un nouvel ID.
     */
    public function reassign(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('manageMembers', $project);

        $validated = $request->validate([
            'from_user_id' => ['required', 'integer', 'min:1'],
            'to_user_id' => ['required', 'integer', 'min:1', 'different:from_user_id'],
            'transfer_membership' => ['nullable', 'boolean'],
            'transfer_tasks' => ['nullable', 'boolean'],
            'transfer_comm' => ['nullable', 'boolean'],
        ]);

        $fromId = (int) $validated['from_user_id'];
        $toId = (int) $validated['to_user_id'];
        $summary = [];

        // ── 1. Membership ────────────────────────────────────────────────
        if ($request->boolean('transfer_membership', true)) {
            $oldMember = ProjectMember::on('tenant')
                ->where('project_id', $project->id)
                ->where('user_id', $fromId)
                ->first();

            if ($oldMember) {
                $existing = ProjectMember::on('tenant')
                    ->where('project_id', $project->id)
                    ->where('user_id', $toId)
                    ->first();

                if ($existing) {
                    // Upgrade vers le rôle le plus élevé
                    $roles = [ProjectRole::VIEWER->value, ProjectRole::MEMBER->value, ProjectRole::OWNER->value];
                    if (array_search($oldMember->role, $roles) > array_search($existing->role, $roles)) {
                        $existing->update(['role' => $oldMember->role]);
                        $summary[] = 'rôle mis à jour';
                    }
                } else {
                    ProjectMember::create([
                        'project_id' => $project->id,
                        'user_id' => $toId,
                        'role' => $oldMember->role,
                    ]);
                    $summary[] = 'membre ajouté';
                }

                $oldMember->delete();
                $summary[] = 'ancien membre retiré';
            }
        }

        // ── 2. Tâches assignées ──────────────────────────────────────────
        if ($request->boolean('transfer_tasks', true)) {
            $count = Task::on('tenant')
                ->where('project_id', $project->id)
                ->where('assigned_to', $fromId)
                ->count();

            if ($count > 0) {
                Task::on('tenant')
                    ->where('project_id', $project->id)
                    ->where('assigned_to', $fromId)
                    ->update(['assigned_to' => $toId]);
                $summary[] = $count.' tâche(s) réassignée(s)';
            }
        }

        // ── 3. Responsabilités actions de communication ──────────────────
        if ($request->boolean('transfer_comm', true)) {
            $count = ProjectCommAction::on('tenant')
                ->where('project_id', $project->id)
                ->where('responsible_id', $fromId)
                ->count();

            if ($count > 0) {
                ProjectCommAction::on('tenant')
                    ->where('project_id', $project->id)
                    ->where('responsible_id', $fromId)
                    ->update(['responsible_id' => $toId]);
                $summary[] = $count.' action(s) comm transférée(s)';
            }
        }

        $fromUser = User::on('tenant')->find($fromId);
        $toUser = User::on('tenant')->find($toId);

        $this->audit->log('project.member.reassigned', auth()->user(), [
            'new' => [
                'project_id' => $project->id,
                'from_user_id' => $fromId,
                'from_user' => $fromUser !== null ? $fromUser->name : "ID $fromId",
                'to_user_id' => $toId,
                'to_user' => $toUser !== null ? $toUser->name : "ID $toId",
                'actions' => $summary,
            ],
        ]);

        $msg = empty($summary)
            ? 'Aucune affectation trouvée pour cet utilisateur.'
            : 'Réaffectation effectuée : '.implode(', ', $summary).'.';

        return back()->with('success', $msg);
    }
}
