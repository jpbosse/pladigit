<?php

namespace App\Models\Tenant;

use App\Enums\ProjectRole;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
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
 *   $project->events()        → HasMany Event (agenda lié au projet)
 *   $project->comments()      → HasMany TaskComment via tasks (agrégé)
 *   $project->budgets()       → HasMany ProjectBudget
 *   $project->stakeholders()  → HasMany ProjectStakeholder
 *   $project->commActions()   → HasMany ProjectCommAction
 *   $project->risks()         → HasMany ProjectRisk
 *   $project->observations()  → HasMany ProjectObservation
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
     * @return BelongsToMany<User, Pivot>
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

    /** @return HasMany<ProjectBudget, $this> */
    public function budgets(): HasMany
    {
        return $this->hasMany(ProjectBudget::class)->orderBy('year')->orderBy('type');
    }

    /** @return HasMany<ProjectStakeholder, $this> */
    public function stakeholders(): HasMany
    {
        return $this->hasMany(ProjectStakeholder::class)->orderByRaw("FIELD(adhesion,'resistant','vigilant','neutre','supporter','champion')");
    }

    /** @return HasMany<ProjectCommAction, $this> */
    public function commActions(): HasMany
    {
        return $this->hasMany(ProjectCommAction::class)->orderBy('planned_at');
    }

    /** @return HasMany<ProjectRisk, $this> */
    public function risks(): HasMany
    {
        return $this->hasMany(ProjectRisk::class)->orderByRaw('FIELD(status,"identified","monitored","mitigated","closed")');
    }

    /** @return HasMany<ProjectObservation, $this> */
    public function observations(): HasMany
    {
        return $this->hasMany(ProjectObservation::class)->latest();
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    /**
     * Projets visibles pour un utilisateur donné.
     *
     * ADR-010 couche 1 :
     *   - Admin / Président / DGS → tous les projets du tenant
     *   - Autres rôles → uniquement les projets dont l'user est membre
     *   - Les brouillons (draft) ne sont visibles que par leur créateur
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

        // Les autres voient :
        // - leurs projets (dont ils sont membre) non-brouillon
        // - leurs brouillons (créés par eux)
        return $query->where(function ($q) use ($user) {
            $q->whereHas('projectMembers', function ($subQ) use ($user) {
                $subQ->where('user_id', $user->id);
            })->where('status', '!=', 'draft');
        })->orWhere('created_by', $user->id);
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
     * Synthèse budgétaire par type — utilisée par la vue élus et la vue finances.
     *
     * @return array{invest: array{planned: float, committed: float, paid: float}, fonct: array{planned: float, committed: float, paid: float}, total: array{planned: float, committed: float, paid: float}}
     */
    public function budgetSummary(): array
    {
        $rows = $this->budgets;

        $sum = fn (string $type, string $field) => (float) $rows
            ->where('type', $type)
            ->sum($field);

        $invest = [
            'planned' => $sum('invest', 'amount_planned'),
            'committed' => $sum('invest', 'amount_committed'),
            'paid' => $sum('invest', 'amount_paid'),
        ];
        $fonct = [
            'planned' => $sum('fonct', 'amount_planned'),
            'committed' => $sum('fonct', 'amount_committed'),
            'paid' => $sum('fonct', 'amount_paid'),
        ];

        return [
            'invest' => $invest,
            'fonct' => $fonct,
            'total' => [
                'planned' => $invest['planned'] + $fonct['planned'],
                'committed' => $invest['committed'] + $fonct['committed'],
                'paid' => $invest['paid'] + $fonct['paid'],
            ],
        ];
    }

    /**
     * Risques actifs (non clôturés), triés par score décroissant.
     *
     * @return \Illuminate\Support\Collection<int, ProjectRisk>
     */
    public function activeRisks(): \Illuminate\Support\Collection
    {
        return $this->risks
            ->whereNotIn('status', ['closed'])
            ->sortByDesc(fn (ProjectRisk $r) => $r->score())
            ->values();
    }

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
            'draft' => 'Brouillon',
            'active' => 'Actif',
            'on_hold' => 'En pause',
            'completed' => 'Terminé',
            'archived' => 'Archivé',
        ];
    }

    /**
     * Couleurs associées aux statuts pour l'affichage.
     *
     * @return array<string, array{bg: string, text: string}>
     */
    public static function statusColors(): array
    {
        return [
            'draft' => ['bg' => '#E2E8F0', 'text' => '#475569'],
            'active' => ['bg' => '#D1FAE5', 'text' => '#065F46'],
            'on_hold' => ['bg' => '#FEF3C7', 'text' => '#92400E'],
            'completed' => ['bg' => '#DBEAFE', 'text' => '#1E40AF'],
            'archived' => ['bg' => '#E2E8F0', 'text' => '#475569'],
        ];
    }

    /**
     * Vérifie si le projet est un brouillon.
     */
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }
}
