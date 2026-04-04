<?php

namespace App\Models\Tenant;

use App\Enums\GedPermissionLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Permission de dossier GED par utilisateur individuel.
 * Prime sur toutes les autres permissions (override absolu).
 *
 * @property int $id
 * @property int $folder_id
 * @property int $user_id
 * @property GedPermissionLevel $level
 */
class GedFolderUserPermission extends Model
{
    protected $connection = 'tenant';

    protected $table = 'ged_folder_user_permissions';

    protected $fillable = [
        'folder_id',
        'user_id',
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

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // ── Scopes ───────────────────────────────────────────────

    public function scopeForFolder($query, int $folderId)
    {
        return $query->where('folder_id', $folderId);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
