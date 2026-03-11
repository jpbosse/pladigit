<?php

namespace App\Models\Tenant;

use App\Enums\AlbumPermissionLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Permission d'album par utilisateur individuel.
 * Prime sur toutes les autres permissions (override absolu).
 *
 * @property int $id
 * @property int $album_id
 * @property int $user_id
 * @property AlbumPermissionLevel $level
 */
class AlbumUserPermission extends Model
{
    protected $connection = 'tenant';

    protected $table = 'album_user_permissions';

    protected $fillable = [
        'album_id',
        'user_id',
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

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // ── Scopes ───────────────────────────────────────────────

    public function scopeForAlbum($query, int $albumId)
    {
        return $query->where('album_id', $albumId);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
