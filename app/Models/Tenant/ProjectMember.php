<?php

// ─────────────────────────────────────────────────────────────────────────────
// app/Models/Tenant/ProjectMember.php
// ─────────────────────────────────────────────────────────────────────────────

namespace App\Models\Tenant;

use App\Enums\ProjectRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Membre d'un projet (table pivot avec données).
 *
 * Utilisé à la place du pivot BelongsToMany quand on a besoin
 * de requêtes sur le rôle directement (ex : ProjectPolicy).
 */
class ProjectMember extends Model
{
    protected $connection = 'tenant';

    protected $table = 'project_members';

    protected $fillable = [
        'project_id',
        'user_id',
        'role',
    ];

    protected $casts = [
        'role' => 'string',
    ];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Retourne le ProjectRole typé.
     */
    public function projectRole(): ProjectRole
    {
        return ProjectRole::from($this->role);
    }
}
