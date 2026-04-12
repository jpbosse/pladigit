<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Nœud de la hiérarchie projet (phase, étape, jalon, livrable…).
 *
 * La hiérarchie est auto-référentielle via parent_id, profondeur max 4 niveaux (0-3).
 * Le label du type est libre (node_type) avec des suggestions : Phase, Étape, Jalon,
 * Livrable, Sprint.
 *
 * Relations :
 *   $ms->parent()    → Nœud parent (BelongsTo self)
 *   $ms->children()  → Nœuds enfants directs (HasMany self)
 *   $ms->tasks()     → Tâches directement rattachées
 *   $ms->project()   → Projet
 *
 * Méthodes :
 *   isRoot()             → true si c'est un nœud racine (parent_id null)
 *   isPhase()            → alias de isRoot() pour compatibilité
 *   isChild()            → alias de !isRoot()
 *   depth()              → profondeur dans l'arbre (0 = racine)
 *   descendantIds()      → IDs de tous les descendants (récursif)
 *   isReached()          → atteint (ou tous les enfants atteints)
 *   isLate()             → due_date dépassée et pas atteint
 *   progressionPercent() → % tâches done (récursif sur tous les descendants)
 *   effectiveColor()     → couleur propre ou héritage du parent
 */
class ProjectMilestone extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    /** @var list<string> */
    protected $fillable = [
        'project_id',
        'parent_id',
        'node_type',
        'title',
        'description',
        'start_date',
        'due_date',
        'reached_at',
        'comment',
        'color',
        'sort_order',
        'manual_progress',
        'responsible_id',
        'department_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'start_date' => 'date',
        'due_date' => 'date',
        'reached_at' => 'datetime',
        'manual_progress' => 'integer',
    ];

    /** Labels de type suggérés dans l'UI. */
    public const TYPE_SUGGESTIONS = ['Phase', 'Étape', 'Jalon', 'Livrable', 'Sprint'];

    /** Profondeur maximale autorisée (4 niveaux = 0, 1, 2, 3). */
    public const MAX_DEPTH = 3;

    // ── Relations ─────────────────────────────────────────────────────────

    /** @return BelongsTo<User, $this> */
    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Nœud parent.
     *
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Enfants directs, triés par sort_order puis due_date.
     *
     * @return HasMany<self>
     */
    public function children(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class, 'parent_id')->orderBy('sort_order')->orderBy('due_date');
    }

    /**
     * Tâches directement rattachées à ce nœud.
     *
     * @return HasMany<Task>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'milestone_id');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\MorphMany<\App\Models\Tenant\ProjectDocument, $this> */
    public function documents(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(\App\Models\Tenant\ProjectDocument::class, 'documentable')->latest();
    }

    // ── Méthodes métier ───────────────────────────────────────────────────

    /**
     * Ce nœud est-il une racine (pas de parent) ?
     */
    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    /**
     * Alias pour compatibilité ascendante.
     */
    public function isPhase(): bool
    {
        return $this->isRoot();
    }

    /**
     * Alias pour compatibilité ascendante.
     */
    public function isChild(): bool
    {
        return ! $this->isRoot();
    }

    /**
     * Profondeur dans l'arbre (0 = racine).
     * Remonte les parents jusqu'à la racine — max MAX_DEPTH itérations.
     */
    public function depth(): int
    {
        $depth = 0;
        $parentId = $this->parent_id;

        while ($parentId !== null && $depth <= self::MAX_DEPTH) {
            $parent = self::on('tenant')->find($parentId);
            if (! $parent) {
                break;
            }
            $parentId = $parent->parent_id;
            $depth++;
        }

        return $depth;
    }

    /**
     * Retourne les IDs de ce nœud et de tous ses descendants (récursif).
     * Nécessite que la relation children soit chargée pour éviter les N+1.
     *
     * @return list<int>
     */
    public function descendantIds(): array
    {
        $ids = [$this->id];

        if ($this->relationLoaded('children')) {
            foreach ($this->children as $child) {
                $ids = array_merge($ids, $child->descendantIds());
            }
        }

        return $ids;
    }

    /**
     * Le nœud est-il atteint ?
     * Pour un nœud avec enfants : atteint si tous les enfants sont atteints.
     */
    public function isReached(): bool
    {
        if ($this->reached_at !== null) {
            return true;
        }

        if ($this->relationLoaded('children') && $this->children->isNotEmpty()) {
            return $this->children->every(fn (self $child) => $child->isReached());
        }

        return false;
    }

    /**
     * Le nœud est-il en retard (due_date dépassée et pas atteint) ?
     */
    public function isLate(): bool
    {
        return ! $this->isReached()
            && $this->due_date !== null
            && $this->due_date->isPast();
    }

    /**
     * Marque le nœud comme atteint maintenant.
     */
    public function markReached(): bool
    {
        return $this->update(['reached_at' => now()]);
    }

    /**
     * Progression (%) du nœud.
     *
     * Règle de priorité :
     *  1. Si manual_progress est défini et > 0 → valeur manuelle (override, même si des tâches existent).
     *     Mettre à 0 pour revenir au calcul automatique.
     *  2. Sinon, si des tâches existent → calcul automatique (% tâches done).
     *  3. Sinon → 0.
     */
    public function progressionPercent(): int
    {
        if ($this->manual_progress !== null && $this->manual_progress > 0) {
            return $this->manual_progress;
        }

        $ids = $this->descendantIds();
        $total = Task::on('tenant')->whereIn('milestone_id', $ids)->count();

        if ($total > 0) {
            $done = Task::on('tenant')
                ->whereIn('milestone_id', $ids)
                ->where('status', 'done')
                ->count();

            return (int) round($done / $total * 100);
        }

        return 0;
    }

    /**
     * Couleur effective : couleur propre ou héritage du parent (récursif).
     */
    public function effectiveColor(): string
    {
        if ($this->color) {
            return $this->color;
        }

        if ($this->relationLoaded('parent') && $this->parent) {
            return $this->parent->effectiveColor();
        }

        return '#EA580C';
    }
}
