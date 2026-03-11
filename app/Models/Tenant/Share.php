<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Partage générique d'un objet avec un utilisateur, un département ou un rôle.
 *
 * shareable_type / shareable_id → l'objet partagé (MediaAlbum, GedDocument, etc.)
 * shared_with_type              → 'user' | 'department' | 'role'
 * shared_with_id                → id user ou department (null si role)
 * shared_with_role              → valeur du rôle (null si user/department)
 */
class Share extends Model
{
    protected $connection = 'tenant';

    protected $table = 'shares';

    protected $fillable = [
        'shareable_type',
        'shareable_id',
        'shared_with_type',
        'shared_with_id',
        'shared_with_role',
        'can_view',
        'can_download',
        'can_edit',
        'can_manage',
        'shared_by',
    ];

    protected $casts = [
        'can_view' => 'boolean',
        'can_download' => 'boolean',
        'can_edit' => 'boolean',
        'can_manage' => 'boolean',
    ];

    // ── Relations ────────────────────────────────────────────

    /** L'objet partagé (polymorphique). */
    public function shareable(): MorphTo
    {
        return $this->morphTo();
    }

    /** Utilisateur destinataire (si shared_with_type = 'user'). */
    public function sharedWithUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_with_id');
    }

    /** Département destinataire (si shared_with_type = 'department'). */
    public function sharedWithDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'shared_with_id');
    }

    /** Utilisateur qui a partagé. */
    public function sharedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_by');
    }

    // ── Scopes ───────────────────────────────────────────────

    public function scopeForUser($query, int $userId)
    {
        return $query->where('shared_with_type', 'user')
            ->where('shared_with_id', $userId);
    }

    public function scopeForRole($query, string $role)
    {
        return $query->where('shared_with_type', 'role')
            ->where('shared_with_role', $role);
    }

    public function scopeForDepartment($query, int $deptId)
    {
        return $query->where('shared_with_type', 'department')
            ->where('shared_with_id', $deptId);
    }

    public function scopeForModel($query, string $type, int $id)
    {
        return $query->where('shareable_type', $type)
            ->where('shareable_id', $id);
    }
}
