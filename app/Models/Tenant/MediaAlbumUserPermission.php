<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaAlbumUserPermission extends Model
{
    protected $connection = 'tenant';
    protected $table = 'media_album_user_permissions';

    protected $fillable = ['album_id', 'user_id', 'can_view', 'can_download', 'can_manage'];

    protected $casts = [
        'can_view'     => 'boolean',
        'can_download' => 'boolean',
        'can_manage'   => 'boolean',
    ];

    public function album(): BelongsTo { return $this->belongsTo(MediaAlbum::class, 'album_id'); }
    public function user(): BelongsTo  { return $this->belongsTo(User::class, 'user_id'); }
}
