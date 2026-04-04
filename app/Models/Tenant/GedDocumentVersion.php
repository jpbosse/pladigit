<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Version archivée d'un document GED.
 * Immuable : pas de updated_at.
 */
class GedDocumentVersion extends Model
{
    protected $connection = 'tenant';

    public $timestamps = false;

    const CREATED_AT = 'created_at';

    protected $fillable = [
        'document_id',
        'version_number',
        'disk_path',
        'size_bytes',
        'mime_type',
        'uploaded_by',
    ];

    protected $casts = [
        'version_number' => 'integer',
        'size_bytes' => 'integer',
        'created_at' => 'datetime',
    ];

    // ── Relations ────────────────────────────────────────────

    /** @return BelongsTo<GedDocument, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(GedDocument::class, 'document_id');
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
