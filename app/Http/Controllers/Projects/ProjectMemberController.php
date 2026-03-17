<?php

namespace App\Http\Controllers\Projects;

use App\Enums\ProjectRole;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Project;
use App\Models\Tenant\ProjectMember;
use App\Models\Tenant\User;
use App\Services\AuditService;
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

        $this->audit->log('project.member.added', auth()->user(), [
            'project_id' => $project->id,
            'user_id' => $validated['user_id'],
            'user_name' => $member?->name,
            'role' => $validated['role'],
        ]);

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

        $this->audit->log('project.member.removed', auth()->user(), [
            'project_id' => $project->id,
            'user_id' => $user->id,
            'user_name' => $user->name,
        ]);

        return back()->with('success', 'Membre retiré du projet.');
    }
}
