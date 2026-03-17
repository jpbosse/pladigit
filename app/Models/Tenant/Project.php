<?php

namespace App\Models\Tenant;

use App\Enums\ProjectRole;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Projet d'un tenant.
 *
 * Relations :
 *   $project->creator()      → User (created_by)
 *   $project->members()      → BelongsToMany User via project_members
 *   $project->projectMembers()→ HasMany ProjectMember (avec le rôle)
 *   $project->tasks()        → HasMany Task
 *   $project->milestones()   → HasMany ProjectMilestone
 *   $project->events()       → HasMany Event (agenda lié au projet)
 *   $project->comments()     → HasMany TaskComment via tasks (agrégé)
 *
 * Scopes :
 *   Project::visibleFor($user)  → projets où l'utilisateur est membre
 *                                 (sauf Admin/Président/DGS : voient tout)
 *   Project::active()           → status = active
 *   Project::byStatus($status)  → filtre par statut
 */
class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        'created_by',
        'name',
        'description',
        'status',
        'start_date',
        'due_date',
        'color',
    ];

    protected $casts = [
        'start_date' => 'date',
        'due_date' => 'date',
    ];

    // ── Relations ─────────────────────────────────────────────────────────

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Membres du projet (table pivot project_members).
     * Inclut le rôle via ->withPivot('role').
     *
     * @return BelongsToMany<User>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    /** @return HasMany<ProjectMember> */
    public function projectMembers(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    /** @return HasMany<Task> */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Tâches racine uniquement (sans parent_task_id).
     *
     * @return HasMany<Task>
     */
    public function rootTasks(): HasMany
    {
        return $this->hasMany(Task::class)->whereNull('parent_task_id');
    }

    /** @return HasMany<ProjectMilestone> */
    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class)->orderBy('due_date');
    }

    /** @return HasMany<Event> */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class)->orderBy('starts_at');
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    /**
     * Projets visibles pour un utilisateur donné.
     *
     * ADR-010 couche 1 :
     *   - Admin / Président / DGS → tous les projets du tenant
     *   - Autres rôles → uniquement les projets dont l'user est membre
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     */
    public function scopeVisibleFor(\Illuminate\Database\Eloquent\Builder $query, User $user): \Illuminate\Database\Eloquent\Builder
    {
        $role = $user->role ? UserRole::tryFrom($user->role) : null;

        // Admin/Président/DGS voient tout
        if ($role && $role->atLeast(UserRole::DGS)) {
            return $query;
        }

        // Les autres ne voient que leurs projets
        return $query->whereHas('projectMembers', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        });
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     */
    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     */
    public function scopeByStatus(\Illuminate\Database\Eloquent\Builder $query, string $status): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', $status);
    }

    // ── Méthodes métier ───────────────────────────────────────────────────

    /**
     * Vérifie si un utilisateur est membre de ce projet.
     */
    public function isMember(User $user): bool
    {
        return $this->projectMembers()
            ->where('user_id', $user->id)
            ->exists();
    }

    /**
     * Retourne le ProjectRole de l'utilisateur dans ce projet.
     * Retourne null s'il n'est pas membre.
     */
    public function memberRole(User $user): ?ProjectRole
    {
        $member = $this->projectMembers()
            ->where('user_id', $user->id)
            ->first();

        return $member ? ProjectRole::tryFrom($member->role) : null;
    }

    /**
     * Progression globale du projet en pourcentage (tâches done / total tâches).
     * Retourne 0 s'il n'y a aucune tâche.
     */
    public function progressionPercent(): int
    {
        $total = $this->tasks()->whereNull('parent_task_id')->count();

        if ($total === 0) {
            return 0;
        }

        $done = $this->tasks()
            ->whereNull('parent_task_id')
            ->where('status', 'done')
            ->count();

        return (int) round($done / $total * 100);
    }

    /**
     * Statistiques des tâches par statut — utilisé par le dashboard et la sidebar projet.
     *
     * @return array{todo: int, in_progress: int, in_review: int, done: int, total: int}
     */
    public function taskStats(): array
    {
        $stats = $this->tasks()
            ->selectRaw('status, count(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        return [
            'todo' => $stats['todo'] ?? 0,
            'in_progress' => $stats['in_progress'] ?? 0,
            'in_review' => $stats['in_review'] ?? 0,
            'done' => $stats['done'] ?? 0,
            'total' => array_sum($stats),
        ];
    }

    /**
     * Libellés français des statuts de projet.
     *
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            'active' => 'Actif',
            'on_hold' => 'En pause',
            'completed' => 'Terminé',
            'archived' => 'Archivé',
        ];
    }
}
