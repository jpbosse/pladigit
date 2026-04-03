<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Liaison entre un document GED et un élément de projet (Project ou Task).
 *
 * Relations :
 *   $link->documentable() → Project|Task (polymorphique)
 *   $link->gedDocument()  → GedDocument
 *   $link->linker()       → User (linked_by)
 */
class ProjectGedLink extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'documentable_type',
        'documentable_id',
        'ged_document_id',
        'linked_by',
    ];

    /** @return MorphTo<Model, $this> */
    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<GedDocument, $this> */
    public function gedDocument(): BelongsTo
    {
        return $this->belongsTo(GedDocument::class, 'ged_document_id');
    }

    /** @return BelongsTo<User, $this> */
    public function linker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_by');
    }
}
