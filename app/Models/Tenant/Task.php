<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Tâche d'un projet.
 *
 * Supporte la hiérarchie récursive (sous-tâches via parent_task_id)
 * et les dépendances (task_dependencies).
 *
 * Relations :
 *   $task->project()         → Project
 *   $task->creator()         → User (created_by)
 *   $task->assignee()        → User|null (assigned_to)
 *   $task->parent()          → Task|null (tâche parente)
 *   $task->children()        → HasMany Task (sous-tâches directes)
 *   $task->allChildren()     → HasMany Task récursif (Eloquent lazy)
 *   $task->comments()        → HasMany TaskComment
 *   $task->milestone()       → ProjectMilestone|null
 *   $task->blockedBy()       → BelongsToMany Task (tâches bloquantes)
 *   $task->blocking()        → BelongsToMany Task (tâches bloquées)
 */
class Task extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        'project_id',
        'created_by',
        'assigned_to',
        'parent_task_id',
        'milestone_id',
        'title',
        'description',
        'status',
        'priority',
        'start_date',
        'due_date',
        'estimated_hours',
        'actual_hours',
        'sort_order',
        'recurrence_type',
        'recurrence_every',
        'recurrence_ends',
        'recurrence_parent_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'due_date' => 'date',
        'recurrence_ends' => 'date',
        'recurrence_every' => 'integer',
    ];

    // ── Relations ─────────────────────────────────────────────────────────

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return BelongsTo<Task, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_task_id');
    }

    /** @return HasMany<Task> */
    public function children(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_task_id');
    }

    /**
     * Chargement récursif des sous-tâches (N niveaux).
     * Usage : $task->load('allChildren.allChildren')
     *
     * @return HasMany<Task>
     */
    public function allChildren(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_task_id')
            ->with('allChildren');
    }

    /** @return HasMany<TaskComment> */
    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->orderBy('created_at');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\MorphMany<\App\Models\Tenant\ProjectDocument, $this> */
    public function documents(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(\App\Models\Tenant\ProjectDocument::class, 'documentable')->latest();
    }

    /** @return BelongsTo<ProjectMilestone, $this> */
    public function milestone(): BelongsTo
    {
        return $this->belongsTo(ProjectMilestone::class);
    }

    /**
     * Tâches dont celle-ci dépend (bloquantes).
     * Cette tâche ne peut démarrer tant qu'elles ne sont pas done.
     *
     * @return BelongsToMany<Task, Pivot>
     */
    public function blockedBy(): BelongsToMany
    {
        return $this->belongsToMany(
            Task::class,
            'task_dependencies',
            'task_id',
            'depends_on_task_id'
        )->withTimestamps();
    }

    /**
     * Tâches bloquées par celle-ci.
     *
     * @return BelongsToMany<Task, Pivot>
     */
    public function blocking(): BelongsToMany
    {
        return $this->belongsToMany(
            Task::class,
            'task_dependencies',
            'depends_on_task_id',
            'task_id'
        )->withTimestamps();
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     */
    public function scopeByStatus(\Illuminate\Database\Eloquent\Builder $query, string $status): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Tâches assignées à un utilisateur précis.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     */
    public function scopeAssignedTo(\Illuminate\Database\Eloquent\Builder $query, int $userId): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('assigned_to', $userId);
    }

    /**
     * Tâches avec une start_date définie — pour le Gantt (ADR-009).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     */
    public function scopeForGantt(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereNotNull('start_date')
            ->whereNotNull('due_date')
            ->orderBy('start_date');
    }

    // ── Méthodes métier ───────────────────────────────────────────────────

    /**
     * Vérifie si cette tâche crée un cycle de dépendance.
     * À appeler AVANT d'insérer dans task_dependencies.
     *
     * Algorithme : BFS depuis depends_on_task_id — si on atteint $this->id, cycle détecté.
     */
    public function blockingTasks(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->blockedBy()
            ->where('status', '!=', 'done')
            ->get(['tasks.id', 'tasks.title', 'tasks.status']);
    }

    public function wouldCreateCycle(int $dependsOnTaskId): bool
    {
        if ($dependsOnTaskId === $this->id) {
            return true; // Auto-dépendance
        }

        $visited = [];
        $queue = [$dependsOnTaskId];

        while (! empty($queue)) {
            $current = array_shift($queue);

            if (isset($visited[$current])) {
                continue;
            }
            $visited[$current] = true;

            $ancestors = \DB::connection('tenant')
                ->table('task_dependencies')
                ->where('task_id', $current)
                ->pluck('depends_on_task_id')
                ->toArray();

            foreach ($ancestors as $ancestor) {
                if ($ancestor === $this->id) {
                    return true; // Cycle détecté
                }
                $queue[] = $ancestor;
            }
        }

        return false;
    }

    /**
     * Progression des sous-tâches directes en pourcentage.
     */
    public function subTaskProgressionPercent(): int
    {
        $total = $this->children()->count();

        if ($total === 0) {
            return 0;
        }

        $done = $this->children()->where('status', 'done')->count();

        return (int) round($done / $total * 100);
    }

    /**
     * Libellés français des statuts de tâche.
     *
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            'todo' => 'À faire',
            'in_progress' => 'En cours',
            'in_review' => 'En revue',
            'done' => 'Terminé',
        ];
    }

    /**
     * Libellés français des priorités.
     *
     * @return array<string, string>
     */
    public static function priorityLabels(): array
    {
        return [
            'low' => 'Basse',
            'medium' => 'Moyenne',
            'high' => 'Haute',
            'urgent' => 'Urgente',
        ];
    }

    /**
     * Classes CSS Tailwind pour les badges de priorité.
     *
     * @return array<string, string>
     */
    public static function priorityClasses(): array
    {
        return [
            'low' => 'bg-green-100 text-green-800',
            'medium' => 'bg-blue-100 text-blue-800',
            'high' => 'bg-amber-100 text-amber-800',
            'urgent' => 'bg-red-100 text-red-800',
        ];
    }
}
