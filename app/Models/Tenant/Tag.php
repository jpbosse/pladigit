<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    protected $connection = 'tenant';

    protected $table = 'media_tags';

    protected $fillable = ['name'];

    /**
     * @return BelongsToMany<MediaItem, $this>
     */
    public function items(): BelongsToMany
    {
        return $this->belongsToMany(MediaItem::class, 'media_item_tag');
    }
}
