<?php

namespace App\Models\Tenant;

use App\Enums\AlbumPermissionLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Permission d'album par sujet (rôle, direction ou service).
 *
 * @property int $id
 * @property int $album_id
 * @property string $subject_type 'role'|'direction'|'service'
 * @property int|null $subject_id ID département si direction/service
 * @property string|null $subject_role Valeur rôle si type=role
 * @property AlbumPermissionLevel $level
 */
class AlbumPermission extends Model
{
    protected $connection = 'tenant';

    protected $table = 'album_permissions';

    protected $fillable = [
        'album_id',
        'subject_type',
        'subject_id',
        'subject_role',
        'level',
    ];

    protected $casts = [
        'level' => AlbumPermissionLevel::class,
    ];

    // ── Relations ────────────────────────────────────────────

    /** @return BelongsTo<MediaAlbum, $this> */
    public function album(): BelongsTo
    {
        return $this->belongsTo(MediaAlbum::class, 'album_id');
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'subject_id');
    }

    // ── Scopes ───────────────────────────────────────────────

    public function scopeForAlbum($query, int $albumId)
    {
        return $query->where('album_id', $albumId);
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
