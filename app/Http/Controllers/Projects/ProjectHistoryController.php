<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\Tenant\AuditLog;
use App\Models\Tenant\Project;
use App\Models\Tenant\User;
use Illuminate\Http\Request;

class ProjectHistoryController extends Controller
{
    private const PROJECT_ACTIONS = [
        'project.created',
        'project.updated',
        'project.duplicated',
        'project.deleted',
        'task.created',
        'task.deleted',
        'task.status_changed',
        'project.member.added',
        'project.member.removed',
        'milestone.reached',
        'milestone.created',
        'phase.created',
        'project.budget.created',
        'project.budget.updated',
        'project.budget.deleted',
    ];

    public static function actionMap(): array
    {
        return [
            'project.created' => ['🎉', '#16A34A', 'Projet créé'],
            'project.updated' => ['✏️',  '#2563EB', 'Projet modifié'],
            'project.duplicated' => ['📋', '#7C3AED', 'Projet dupliqué'],
            'project.deleted' => ['🗑',  '#DC2626', 'Projet supprimé'],
            'task.created' => ['➕', '#0891B2', 'Tâche créée'],
            'task.deleted' => ['🗑',  '#DC2626', 'Tâche supprimée'],
            'task.status_changed' => ['🔄', '#D97706', 'Statut modifié'],
            'project.member.added' => ['👤', '#16A34A', 'Membre ajouté'],
            'project.member.removed' => ['👤', '#DC2626', 'Membre retiré'],
            'milestone.reached' => ['🏁', '#16A34A', 'Jalon atteint'],
            'milestone.created' => ['🚩', '#0891B2', 'Jalon créé'],
            'phase.created' => ['📌', '#7C3AED', 'Phase créée'],
            'project.budget.created' => ['💰', '#0891B2', 'Budget ajouté'],
            'project.budget.updated' => ['💰', '#D97706', 'Budget modifié'],
            'project.budget.deleted' => ['💰', '#DC2626', 'Budget supprimé'],
        ];
    }

    public function index(Request $request, Project $project)
    {
        $this->authorize('view', $project);

        /** @var User $user */
        $user = auth()->user();
        $canSeeAll = $this->canSeeAll($user, $project);
        $filterAction = $request->get('action');

        // Requête sécurisée — filtre project_id via LIKE sur new_values
        // (évite JSON_EXTRACT qui plante sur les new_values null)
        $query = AuditLog::on('tenant')
            ->whereIn('action', self::PROJECT_ACTIONS)
            ->where('new_values', 'LIKE', '%"project_id": '.$project->id.'%')
            ->orderByDesc('created_at');

        if (! $canSeeAll) {
            $query->where('user_id', $user->id);
        }

        if ($filterAction && in_array($filterAction, self::PROJECT_ACTIONS, true)) {
            $query->where('action', $filterAction);
        }

        $logs = $query->paginate(25)->withQueryString();

        $availableActions = AuditLog::on('tenant')
            ->whereIn('action', self::PROJECT_ACTIONS)
            ->where('new_values', 'LIKE', '%"project_id": '.$project->id.'%')
            ->when(! $canSeeAll, fn ($q) => $q->where('user_id', $user->id))
            ->selectRaw('action, COUNT(*) as cnt')
            ->groupBy('action')
            ->orderByDesc('cnt')
            ->pluck('cnt', 'action');

        return view('projects.partials._historique', compact(
            'project', 'logs', 'canSeeAll', 'availableActions', 'filterAction'
        ));
    }

    private function canSeeAll(User $user, Project $project): bool
    {
        if ($user->role && in_array($user->role, ['admin', 'president', 'dgs', 'resp_direction', 'resp_service'], true)) {
            return true;
        }
        $role = $project->memberRole($user);

        return $role === \App\Enums\ProjectRole::OWNER;
    }
}
