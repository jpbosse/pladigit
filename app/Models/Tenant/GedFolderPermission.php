<?php

namespace App\Models\Tenant;

use App\Enums\GedPermissionLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Permission de dossier GED par sujet (rôle, direction ou service).
 *
 * @property int $id
 * @property int $folder_id
 * @property string $subject_type 'role'|'direction'|'service'
 * @property int|null $subject_id ID département si direction/service
 * @property string|null $subject_role Valeur rôle si type=role
 * @property GedPermissionLevel $level
 */
class GedFolderPermission extends Model
{
    protected $connection = 'tenant';

    protected $table = 'ged_folder_permissions';

    protected $fillable = [
        'folder_id',
        'subject_type',
        'subject_id',
        'subject_role',
        'level',
    ];

    protected $casts = [
        'level' => GedPermissionLevel::class,
    ];

    // ── Relations ────────────────────────────────────────────

    /** @return BelongsTo<GedFolder, $this> */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(GedFolder::class, 'folder_id');
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'subject_id');
    }

    // ── Scopes ───────────────────────────────────────────────

    public function scopeForFolder($query, int $folderId)
    {
        return $query->where('folder_id', $folderId);
    }

    public function scopeForRole($query, string $role)
    {
        return $query->where('subject_type', 'role')
            ->where('subject_role', $role);
    }

    public function scopeForDepartment($query, int $deptId)
    {
        return $query->whereIn('subject_type', ['direction', 'service'])
            ->where('subject_id', $deptId);
    }
}
