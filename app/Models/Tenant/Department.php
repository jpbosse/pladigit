<?php

namespace App\Models\Tenant;

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
    use SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        'name',
        'type',
        'parent_id',
        'created_by',
    ];

    // ── Relations ────────────────────────────────────────────

    /**
     * Tous les utilisateurs membres de ce département (via pivot).
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_department')
            ->withPivot('is_manager')
            ->withTimestamps();
    }

    /**
     * Uniquement les responsables (is_manager = true).
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
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'parent_id');
    }

    /**
     * Les services enfants (pour une direction).
     */
    public function children(): HasMany
    {
        return $this->hasMany(Department::class, 'parent_id');
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
        if ($this->isService() && $this->parent) {
            return $this->parent->name.' — '.$this->name;
        }

        return $this->name;
    }
}
