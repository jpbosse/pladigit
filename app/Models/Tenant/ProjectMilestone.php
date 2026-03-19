<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Jalon (milestone) d'un projet — supporte deux niveaux :
 *
 *   Phase  : parent_id = null, peut avoir des jalons enfants.
 *   Jalon  : parent_id = id d'une phase.
 *
 * Un jalon sans enfants ET sans parent_id est un jalon autonome classique.
 *
 * Relations :
 *   $ms->parent()    → Phase parente (BelongsTo self)
 *   $ms->children()  → Jalons enfants (HasMany self)
 *   $ms->tasks()     → Tâches rattachées
 *   $ms->project()   → Projet
 *
 * Méthodes :
 *   isPhase()           → true si c'est une phase (parent_id null + a des enfants)
 *   isReached()         → reached_at non null
 *   isLate()            → due_date dépassée et pas atteint
 *   progressionPercent()→ % tâches done (récursif pour les phases)
 */
class ProjectMilestone extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        'project_id',
        'parent_id',
        'title',
        'description',
        'start_date',
        'due_date',
        'reached_at',
        'color',
        'sort_order',
    ];

    protected $casts = [
        'start_date' => 'date',
        'due_date'   => 'date',
        'reached_at' => 'datetime',
    ];

    // ── Relations ─────────────────────────────────────────────────────────

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Phase parente (null si c'est déjà une phase ou un jalon autonome).
     *
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Jalons enfants (jalons appartenant à cette phase).
     *
     * @return HasMany<self>
     */
    public function children(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class, 'parent_id')->orderBy('sort_order')->orderBy('due_date');
    }

    /**
     * Tâches directement rattachées à ce jalon.
     *
     * @return HasMany<Task>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'milestone_id');
    }

    // ── Méthodes métier ───────────────────────────────────────────────────

    /**
     * Ce nœud est-il une phase ?
     * Convention : parent_id null ET (a des enfants OU a été créé comme phase).
     * On détecte via parent_id null — l'UI garantit la cohérence.
     */
    public function isPhase(): bool
    {
        return $this->parent_id === null;
    }

    /**
     * Ce nœud est-il un jalon enfant d'une phase ?
     */
    public function isChild(): bool
    {
        return $this->parent_id !== null;
    }

    /**
     * Le jalon / la phase est-il atteint ?
     * Pour une phase : atteinte quand tous ses jalons enfants sont atteints.
     */
    public function isReached(): bool
    {
        if ($this->reached_at !== null) {
            return true;
        }

        // Phase sans enfants chargés : non atteinte
        if ($this->isPhase() && $this->relationLoaded('children') && $this->children->isNotEmpty()) {
            return $this->children->every(fn (self $child) => $child->isReached());
        }

        return false;
    }

    /**
     * Le jalon est-il en retard (due_date dépassée et pas atteint) ?
     */
    public function isLate(): bool
    {
        return ! $this->isReached()
            && $this->due_date !== null
            && $this->due_date->isPast();
    }

    /**
     * Marque le jalon comme atteint maintenant.
     */
    public function markReached(): bool
    {
        return $this->update(['reached_at' => now()]);
    }

    /**
     * Progression des tâches (% done).
     * Pour une phase : agrège les tâches de tous ses jalons enfants.
     */
    public function progressionPercent(): int
    {
        if ($this->isPhase() && $this->relationLoaded('children') && $this->children->isNotEmpty()) {
            $total = $this->children->sum(fn (self $child) => $child->tasks()->count());
            if ($total === 0) {
                return 0;
            }
            $done = $this->children->sum(fn (self $child) => $child->tasks()->where('status', 'done')->count());

            return (int) round($done / $total * 100);
        }

        $total = $this->tasks()->count();
        if ($total === 0) {
            return 0;
        }

        return (int) round($this->tasks()->where('status', 'done')->count() / $total * 100);
    }

    /**
     * Couleur effective : couleur propre ou héritage de la phase parente.
     */
    public function effectiveColor(): string
    {
        if ($this->color) {
            return $this->color;
        }

        if ($this->relationLoaded('parent') && $this->parent) {
            return $this->parent->color ?? '#EA580C';
        }

        return '#EA580C';
    }
}
