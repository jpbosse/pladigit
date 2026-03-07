<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Représente une Direction ou un Service dans un tenant.
 *
 * Hiérarchie :
 *   Direction (type='direction', parent_id=null)
 *     └── Service (type='service', parent_id=direction.id)
 *
 * Accès :
 *   $dept->members()   → tous les utilisateurs membres
 *   $dept->managers()  → les responsables (is_manager=true)
 *   $dept->children()  → les services enfants (si direction)
 *   $dept->parent()    → la direction parente (si service)
 */
class Department extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        'name',
        'type',
        'label',
        'color',
        'is_transversal',
        'sort_order',
        'parent_id',
        'created_by',
    ];

    protected $casts = [
        'is_transversal' => 'boolean',
        'sort_order' => 'integer',
    ];

    // ── Relations ────────────────────────────────────────────

    /**
     * Tous les utilisateurs membres de ce département (via pivot).
     *
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_department')
            ->withPivot('is_manager')
            ->withTimestamps();
    }

    /**
     * Uniquement les responsables (is_manager = true).
     *
     * @return BelongsToMany<User, $this>
     */
    public function managers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_department')
            ->withPivot('is_manager')
            ->wherePivot('is_manager', true)
            ->withTimestamps();
    }

    /**
     * Uniquement les agents (is_manager = false).
     *
     * @return BelongsToMany<User, $this>
     */
    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_department')
            ->withPivot('is_manager')
            ->wherePivot('is_manager', false)
            ->withTimestamps();
    }

    /**
     * La direction parente (pour un service).
     *
     * @return BelongsTo<Department, $this>
     */
    public function parentDept(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'parent_id');
    }

    /**
     * Les services enfants (pour une direction).
     *
     * @return HasMany<Department, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Department::class, 'parent_id')->orderBy('name');
    }

    public function allChildren(): HasMany
    {
        return $this->children()->with('allChildren.members', 'allChildren.managers');
    }


public function ancestors(): array
{
    $ancestors = [];
    $current = $this->parentDept;
    while ($current) {
        array_unshift($ancestors, $current);
        $current = $current->parentDept;
    }
    return $ancestors;
}


    // Couleur par défaut selon le label
    public function getColorAttribute($value): string
    {
        if ($value) {
            return $value;
        }

        return match (strtolower($this->label ?? $this->type ?? '')) {
            'pôle', 'pole' => '#7c3aed',
            'direction' => '#1e40af',
            'service' => '#0369a1',
            'bureau', 'cellule' => '#0891b2',
            default => '#475569',
        };
    }

    // ── Scopes ───────────────────────────────────────────────

    public function scopeDirections($query)
    {
        return $query->where('type', 'direction');
    }

    public function scopeServices($query)
    {
        return $query->where('type', 'service');
    }

    // ── Helpers ──────────────────────────────────────────────

    public function isDirection(): bool
    {
        return $this->type === 'direction';
    }

    public function isService(): bool
    {
        return $this->type === 'service';
    }

    /**
     * Label affiché dans les selects.
     * Ex : "DST — Service Voirie" ou "Direction RH"
     */
    public function fullLabel(): string
    {
        /** @var Department|null $parent */
        $parent = $this->parent;
        if ($this->isService() && $parent instanceof Department) {
            return $parent->name.' — '.$this->name;
        }

        return $this->name;
    }
}
