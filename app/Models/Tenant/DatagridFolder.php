<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Dossier pour organiser les grilles DataGrid dans la sidebar (ADR-039 §2.4).
 *
 * @property int $id
 * @property string $label
 * @property int|null $parent_id
 * @property int $sort_order
 * @property int|null $created_by
 */
class DatagridFolder extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'label',
        'parent_id',
        'sort_order',
        'created_by',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    /** @return BelongsTo<DatagridFolder, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(DatagridFolder::class, 'parent_id');
    }

    /** @return HasMany<DatagridFolder, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(DatagridFolder::class, 'parent_id')->orderBy('sort_order');
    }

    /** @return HasMany<DatagridTable, $this> */
    public function tables(): HasMany
    {
        return $this->hasMany(DatagridTable::class, 'folder_id')->orderBy('label');
    }
}
