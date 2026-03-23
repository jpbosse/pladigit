<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Project;
use App\Models\Tenant\Task;
use Illuminate\Http\Request;

class TaskDependencyController extends Controller
{
    public function store(Request $request, Project $project, Task $task)
    {
        $this->authorize('update', $task);

        $validated = $request->validate([
            'predecessor_id' => ['required', 'integer', 'exists:tenant.tasks,id'],
        ]);

        $predecessorId = (int) $validated['predecessor_id'];

        $predecessor = Task::on('tenant')
            ->where('id', $predecessorId)
            ->where('project_id', $project->id)
            ->firstOrFail();

        if ($predecessor->id === $task->id) {
            return response()->json(['error' => "Une tâche ne peut pas dépendre d'elle-même."], 422);
        }

        if ($task->wouldCreateCycle($predecessorId)) {
            return response()->json(['error' => 'Cette dépendance créerait une dépendance circulaire.'], 422);
        }

        \DB::connection('tenant')->table('task_dependencies')->updateOrInsert(
            ['task_id' => $task->id, 'depends_on_task_id' => $predecessorId],
            ['created_at' => now(), 'updated_at' => now()]
        );

        return response()->json([
            'success' => true,
            'predecessor' => [
                'id' => $predecessor->id,
                'title' => $predecessor->title,
                'status' => $predecessor->status,
            ],
        ]);
    }

    public function destroy(Project $project, Task $task, Task $predecessor)
    {
        $this->authorize('update', $task);

        \DB::connection('tenant')->table('task_dependencies')
            ->where('task_id', $task->id)
            ->where('depends_on_task_id', $predecessor->id)
            ->delete();

        return response()->json(['success' => true]);
    }
}
