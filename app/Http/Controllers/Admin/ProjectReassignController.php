<?php

namespace App\Http\Controllers\Admin;

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
 * Réaffectation inter-projets — réservé aux admins organisation.
 *
 * Deux actions :
 *   1. Attribuer les éléments sans propriétaire d'un projet à quelqu'un.
 *   2. Transférer toutes les affectations d'un compte vers un autre.
 */
class ProjectReassignController extends Controller
{
    public function __construct(
        private readonly AuditService $audit,
    ) {}

    public function index(Request $request)
    {
        $activeUsers = User::on('tenant')->orderBy('name')->get();
        $projects = Project::on('tenant')->orderBy('name')->get(['id', 'name']);

        $selectedProjectId = $request->integer('project_id') ?: null;
        $selectedProject = null;
        $unownedTasks = collect();
        $unownedComms = collect();

        if ($selectedProjectId !== null) {
            $selectedProject = Project::on('tenant')->find($selectedProjectId);

            if ($selectedProject !== null) {
                $unownedTasks = Task::on('tenant')
                    ->where('project_id', $selectedProjectId)
                    ->whereNull('assigned_to')
                    ->whereNull('deleted_at')
                    ->orderBy('title')
                    ->get(['id', 'title']);

                $unownedComms = ProjectCommAction::on('tenant')
                    ->where('project_id', $selectedProjectId)
                    ->whereNull('responsible_id')
                    ->orderBy('title')
                    ->get(['id', 'title']);
            }
        }

        return view('admin.projects.reassign', compact(
            'activeUsers',
            'projects',
            'selectedProjectId',
            'selectedProject',
            'unownedTasks',
            'unownedComms',
        ));
    }

    /**
     * Attribue les tâches / actions comm sans propriétaire à un utilisateur.
     */
    public function storeUnowned(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'project_id' => ['required', 'integer', 'exists:tenant.projects,id'],
            'to_user_id' => ['required', 'integer', 'exists:tenant.users,id'],
            'assign_tasks' => ['nullable', 'boolean'],
            'assign_comm' => ['nullable', 'boolean'],
        ]);

        $pid = (int) $validated['project_id'];
        $toId = (int) $validated['to_user_id'];
        $summary = [];

        if ($request->boolean('assign_tasks', true)) {
            $count = Task::on('tenant')
                ->where('project_id', $pid)
                ->whereNull('assigned_to')
                ->whereNull('deleted_at')
                ->count();

            if ($count > 0) {
                Task::on('tenant')
                    ->where('project_id', $pid)
                    ->whereNull('assigned_to')
                    ->whereNull('deleted_at')
                    ->update(['assigned_to' => $toId]);
                $summary[] = "$count tâche(s) attribuée(s)";
            }
        }

        if ($request->boolean('assign_comm', true)) {
            $count = ProjectCommAction::on('tenant')
                ->where('project_id', $pid)
                ->whereNull('responsible_id')
                ->count();

            if ($count > 0) {
                ProjectCommAction::on('tenant')
                    ->where('project_id', $pid)
                    ->whereNull('responsible_id')
                    ->update(['responsible_id' => $toId]);
                $summary[] = "$count action(s) comm attribuée(s)";
            }
        }

        $toUser = User::on('tenant')->find($toId);

        $this->audit->log('admin.project.assign_unowned', auth()->user(), [
            'new' => [
                'project_id' => $pid,
                'to_user_id' => $toId,
                'to_user' => $toUser !== null ? $toUser->name : "ID $toId",
                'actions' => $summary,
            ],
        ]);

        $msg = empty($summary)
            ? 'Aucun élément sans propriétaire trouvé dans ce projet.'
            : 'Attribution effectuée : '.implode(', ', $summary).'.';

        return redirect()->route('admin.projects.reassign.index', ['project_id' => $pid])
            ->with('success', $msg);
    }

    /**
     * Transfère toutes les affectations d'un compte vers un autre.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'from_user_id' => ['required', 'integer', 'min:1'],
            'to_user_id' => ['required', 'integer', 'min:1', 'different:from_user_id'],
            'project_id' => ['nullable', 'integer', 'min:1'],
            'transfer_membership' => ['nullable', 'boolean'],
            'transfer_tasks' => ['nullable', 'boolean'],
            'transfer_comm' => ['nullable', 'boolean'],
        ]);

        $fromId = (int) $validated['from_user_id'];
        $toId = (int) $validated['to_user_id'];
        $projectId = isset($validated['project_id']) ? (int) $validated['project_id'] : null;
        $summary = [];

        $projectIds = $projectId !== null
            ? collect([$projectId])
            : Project::on('tenant')->pluck('id');

        foreach ($projectIds as $pid) {

            // 1. Membership
            if ($request->boolean('transfer_membership', true)) {
                $oldMember = ProjectMember::on('tenant')
                    ->where('project_id', $pid)
                    ->where('user_id', $fromId)
                    ->first();

                if ($oldMember) {
                    $existing = ProjectMember::on('tenant')
                        ->where('project_id', $pid)
                        ->where('user_id', $toId)
                        ->first();

                    if ($existing) {
                        $roles = [ProjectRole::VIEWER->value, ProjectRole::MEMBER->value, ProjectRole::OWNER->value];
                        if (array_search($oldMember->role, $roles) > array_search($existing->role, $roles)) {
                            $existing->update(['role' => $oldMember->role]);
                            $summary[] = "projet $pid : rôle mis à jour";
                        }
                    } else {
                        ProjectMember::create([
                            'project_id' => $pid,
                            'user_id' => $toId,
                            'role' => $oldMember->role,
                        ]);
                        $summary[] = "projet $pid : membre ajouté";
                    }

                    $oldMember->delete();
                }
            }

            // 2. Tâches
            if ($request->boolean('transfer_tasks', true)) {
                $count = Task::on('tenant')
                    ->where('project_id', $pid)
                    ->where('assigned_to', $fromId)
                    ->count();

                if ($count > 0) {
                    Task::on('tenant')
                        ->where('project_id', $pid)
                        ->where('assigned_to', $fromId)
                        ->update(['assigned_to' => $toId]);
                    $summary[] = "projet $pid : $count tâche(s) réassignée(s)";
                }
            }

            // 3. Actions comm
            if ($request->boolean('transfer_comm', true)) {
                $count = ProjectCommAction::on('tenant')
                    ->where('project_id', $pid)
                    ->where('responsible_id', $fromId)
                    ->count();

                if ($count > 0) {
                    ProjectCommAction::on('tenant')
                        ->where('project_id', $pid)
                        ->where('responsible_id', $fromId)
                        ->update(['responsible_id' => $toId]);
                    $summary[] = "projet $pid : $count action(s) comm transférée(s)";
                }
            }
        }

        $fromUser = User::on('tenant')->withTrashed()->find($fromId);
        $toUser = User::on('tenant')->find($toId);

        $this->audit->log('admin.project.reassign', auth()->user(), [
            'new' => [
                'from_user_id' => $fromId,
                'from_user' => $fromUser !== null ? $fromUser->name : "ID $fromId",
                'to_user_id' => $toId,
                'to_user' => $toUser !== null ? $toUser->name : "ID $toId",
                'project_id' => $projectId ?? 'tous',
                'actions' => $summary,
            ],
        ]);

        $msg = empty($summary)
            ? 'Aucune affectation trouvée pour cet utilisateur dans le périmètre sélectionné.'
            : 'Réaffectation effectuée : '.implode(', ', $summary).'.';

        return back()->with('success', $msg);
    }
}
