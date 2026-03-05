<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Album photo d'un tenant.
 *
 * Relations :
 *   $album->creator()     → User qui a créé l'album
 *   $album->items()       → tous les MediaItem de l'album
 *   $album->shareLinks()  → liens de partage temporaires
 *
 * Scopes :
 *   MediaAlbum::visibleFor($user)  → albums accessibles selon le rôle
 */
class MediaAlbum extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        'created_by',
        'name',
        'description',
        'cover_path',
        'visibility',
    ];

    protected $casts = [
        'visibility' => 'string',
    ];

    // ── Relations ────────────────────────────────────────────

    /**
     * Utilisateur créateur de l'album.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Tous les médias de l'album (non supprimés).
     *
     * @return HasMany<MediaItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(MediaItem::class, 'album_id');
    }

    /**
     * Liens de partage temporaires de l'album.
     *
     * @return HasMany<MediaShareLink, $this>
     */
    public function shareLinks(): HasMany
    {
        return $this->hasMany(MediaShareLink::class, 'album_id');
    }

    // ── Scopes ───────────────────────────────────────────────

    /**
     * Albums publics (visibles par tous).
     */
    public function scopePublic($query)
    {
        return $query->where('visibility', 'public');
    }

    /**
     * Albums restreints ou publics (exclus les albums privés d'autres users).
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     */
    public function scopeVisibleFor($query, User $user)
    {
        return $query->where(function ($q) use ($user) {
            $q->where('visibility', 'public')
                ->orWhere('visibility', 'restricted')
                ->orWhere(function ($q2) use ($user) {
                    // Albums privés : uniquement les siens
                    $q2->where('visibility', 'private')
                        ->where('created_by', $user->id);
                });
        });
    }

    // ── Helpers ──────────────────────────────────────────────

    /**
     * Nombre de médias dans l'album (sans requête supplémentaire si déjà chargé).
     */
    public function itemCount(): int
    {
        return $this->relationLoaded('items')
            ? $this->items->count()
            : $this->items()->count();
    }

    /**
     * Label de visibilité en français.
     */
    public function visibilityLabel(): string
    {
        return match ($this->visibility) {
            'public' => 'Public',
            'restricted' => 'Restreint',
            'private' => 'Privé',
            default => $this->visibility,
        };
    }
}
