<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Project;
use App\Models\Tenant\Task;
use App\Models\Tenant\User;
use App\Services\AuditService;
use Illuminate\Http\Request;

/**
 * CRUD des tâches d'un projet.
 */
class TaskController extends Controller
{
    public function __construct(
        private readonly AuditService $audit,
    ) {}

    /**
     * Retourne les détails d'une tâche en JSON — utilisé par le slide-over Alpine.js.
     *
     * GET /projects/{project}/tasks/{task}
     */
    public function show(Project $project, Task $task)
    {
        $this->authorize('view', $task);

        $task->load(['assignee:id,name', 'milestone:id,title', 'children:id,parent_task_id,status']);

        $comments = $task->comments()
            ->with('author:id,name')
            ->latest()
            ->get()
            ->map(fn ($c) => [
                'id'         => $c->id,
                'author'     => $c->author?->name ?? '—',
                'body'       => $c->body,
                'created_at' => $c->created_at->locale('fr')->diffForHumans(),
                'is_mine'    => $c->user_id === auth()->id(),
            ]);

        return response()->json([
            'task' => [
                'id'              => $task->id,
                'title'           => $task->title,
                'description'     => $task->description,
                'status'          => $task->status,
                'priority'        => $task->priority,
                'start_date'      => $task->start_date?->format('Y-m-d'),
                'due_date'        => $task->due_date?->format('Y-m-d'),
                'estimated_hours' => $task->estimated_hours,
                'actual_hours'    => $task->actual_hours,


'assigned_to'     => $task->assigned_to,
'assignee'        => $task->assignee ? ['id' => $task->assignee->id, 'name' => $task->assignee->name] : null,
'milestone_id'    => $task->milestone_id,
'milestone'       => $task->milestone ? ['id' => $task->milestone->id, 'title' => $task->milestone->title] : null,


                'subtasks_total'  => $task->children->count(),
                'subtasks_done'   => $task->children->where('status', 'done')->count(),
            ],
            'comments' => $comments,
        ]);
    }

    /**
     * Création d'une tâche dans un projet.
     */
    public function store(Request $request, Project $project)
    {
        $this->authorize('createTask', $project);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', 'in:todo,in_progress,in_review,done'],
            'priority' => ['required', 'in:low,medium,high,urgent'],
            'assigned_to' => ['nullable', 'exists:tenant.users,id'],
            'parent_task_id' => ['nullable', 'exists:tenant.tasks,id'],
            'milestone_id' => ['nullable', 'exists:tenant.project_milestones,id'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'estimated_hours' => ['nullable', 'integer', 'min:1', 'max:9999'],
        ]);

        /** @var User $user */
        $user = auth()->user();

        // sort_order : ajouter en fin de colonne
        $maxOrder = $project->tasks()
            ->where('status', $validated['status'])
            ->whereNull('parent_task_id')
            ->max('sort_order') ?? -1;

        $task = $project->tasks()->create([
            ...$validated,
            'created_by' => $user->id,
            'sort_order' => $maxOrder + 1,
        ]);

        $this->audit->log('task.created', auth()->user(), [
            'project_id' => $project->id,
            'task_id' => $task->id,
            'task_title' => $task->title,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'task_id' => $task->id]);
        }

        return back()->with('success', 'Tâche créée.');
    }

    /**
     * Modification d'une tâche.
     */
    public function update(Request $request, Project $project, Task $task)
    {
        $this->authorize('update', $task);

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['sometimes', 'required', 'in:todo,in_progress,in_review,done'],
            'priority' => ['sometimes', 'required', 'in:low,medium,high,urgent'],
            'assigned_to' => ['nullable', 'exists:tenant.users,id'],
            'milestone_id' => ['nullable', 'exists:tenant.project_milestones,id'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'estimated_hours' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'actual_hours' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $oldStatus = $task->status;
        $task->update($validated);

        if (isset($validated['status']) && $validated['status'] !== $oldStatus) {
            $this->audit->log('task.status_changed', auth()->user(), [
                'project_id' => $project->id,
                'task_id' => $task->id,
                'from' => $oldStatus,
                'to' => $validated['status'],
            ]);
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Tâche mise à jour.');
    }

    /**
     * Mise à jour des dates depuis le Gantt (Alpine.js drag horizontal).
     * PATCH /projects/{project}/tasks/{task}/dates
     */
    public function updateDates(Request $request, Project $project, Task $task)
    {
        $this->authorize('updateDates', $task);

        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $task->update($validated);

        return response()->json(['success' => true]);
    }

    /**
     * Suppression (soft delete) d'une tâche.
     */
    public function destroy(Project $project, Task $task)
    {
        $this->authorize('delete', $task);

        $task->delete();

        $this->audit->log('task.deleted', auth()->user(), [
            'project_id' => $project->id,
            'task_id' => $task->id,
            'task_title' => $task->title,
        ]);

        if (request()->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Tâche supprimée.');
    }
}
