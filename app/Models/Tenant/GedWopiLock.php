<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Verrou WOPI sur un document GED.
 *
 * Un seul verrou actif par document (contrainte UNIQUE sur document_id).
 * Les verrous expirés sont purgés à la prochaine opération WOPI sur le document.
 */
class GedWopiLock extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'document_id',
        'lock_id',
        'expires_at',
        'locked_by',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /** @return BelongsTo<GedDocument, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(GedDocument::class, 'document_id');
    }

    /** @return BelongsTo<User, $this> */
    public function locker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
