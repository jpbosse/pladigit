<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaAlbumPermission extends Model
{
    protected $connection = 'tenant';
    protected $table = 'media_album_permissions';

    protected $fillable = ['album_id', 'role', 'can_view', 'can_download', 'can_manage'];

    protected $casts = [
        'can_view'     => 'boolean',
        'can_download' => 'boolean',
        'can_manage'   => 'boolean',
    ];

    public function album(): BelongsTo
    {
        return $this->belongsTo(MediaAlbum::class, 'album_id');
    }
}
