<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Token WOPI pour l'ouverture d'un document GED dans Collabora Online.
 *
 * @property int $id
 * @property int $document_id
 * @property int $user_id
 * @property string $token
 * @property Carbon $expires_at
 */
class GedWopiToken extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['document_id', 'user_id', 'token', 'expires_at'];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /** @return BelongsTo<GedDocument, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(GedDocument::class, 'document_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
