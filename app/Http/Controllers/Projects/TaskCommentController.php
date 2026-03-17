<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Project;
use App\Models\Tenant\Task;
use App\Models\Tenant\TaskComment;
use App\Models\Tenant\User;
use Illuminate\Http\Request;

/**
 * Gestion des commentaires sur les tâches.
 * Seul l'auteur peut supprimer son commentaire.
 */
class TaskCommentController extends Controller
{
    public function store(Request $request, Project $project, Task $task)
    {
        $this->authorize('comment', $task);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        /** @var User $user */
        $user = auth()->user();

        $comment = $task->comments()->create([
            'user_id' => $user->id,
            'body' => $validated['body'],
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'comment_id' => $comment->id,
                'author' => $user->name,
                'body' => $comment->body,
                'created_at' => $comment->created_at->diffForHumans(),
            ]);
        }

        return back()->with('success', 'Commentaire ajouté.');
    }

    public function destroy(Project $project, Task $task, TaskComment $comment)
    {
        /** @var User $user */
        $user = auth()->user();

        // Seul l'auteur peut supprimer — Admin+ intercepté par TaskPolicy::before()
        if ($comment->user_id !== $user->id) {
            abort(403, 'Vous ne pouvez supprimer que vos propres commentaires.');
        }

        $comment->delete();

        if (request()->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Commentaire supprimé.');
    }
}
