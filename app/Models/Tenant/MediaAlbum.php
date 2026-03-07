<?php

namespace App\Models\Tenant;

use App\Enums\UserRole;
use App\Models\Concerns\Shareable;
use App\Services\ShareService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Album photo d'un tenant.
 *
 * Relations :
 *   $album->creator()   → User qui a créé l'album
 *   $album->items()     → tous les MediaItem de l'album
 *   $album->shareLinks() → liens de partage temporaires
 *   $album->shares()    → partages (via trait Shareable)
 */
class MediaAlbum extends Model
{
    use HasFactory, Shareable, SoftDeletes;

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

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<MediaItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(MediaItem::class, 'album_id');
    }

    /** @return HasMany<MediaShareLink, $this> */
    public function shareLinks(): HasMany
    {
        return $this->hasMany(MediaShareLink::class, 'album_id');
    }

    // ── Scopes ───────────────────────────────────────────────

    public function scopePublic($query)
    {
        return $query->where('visibility', 'public');
    }

    public function scopeVisibleFor($query, User $user)
    {
        return $query->where(function ($q) use ($user) {
            $q->where('visibility', 'public')
                ->orWhere('visibility', 'restricted')
                ->orWhere(function ($q2) use ($user) {
                    $q2->where('visibility', 'private')
                        ->where('created_by', $user->id);
                });
        });
    }

    // ── Helpers ──────────────────────────────────────────────

    public function itemCount(): int
    {
        return $this->relationLoaded('items')
            ? $this->items->count()
            : $this->items()->count();
    }

    public function visibilityLabel(): string
    {
        return match ($this->visibility) {
            'public' => 'Public',
            'restricted' => 'Restreint',
            'private' => 'Privé',
            default => $this->visibility,
        };
    }

    /**
     * Vérifie si un utilisateur a un droit sur cet album.
     * Délègue au ShareService (résolution user → dept → rôle).
     *
     * @param  'can_view'|'can_download'|'can_edit'|'can_manage'  $ability
     */
    public function userCan(User $user, string $ability): bool
    {
        // Admin/Président/DGS — accès total
        if ($user->role && UserRole::from($user->role)->atLeast(UserRole::DGS)) {
            return true;
        }

        // Album public → can_view pour tous
        if ($ability === 'can_view' && $this->visibility === 'public') {
            return true;
        }

        // Album privé → créateur uniquement
        if ($this->visibility === 'private') {
            return $this->created_by === $user->id;
        }

        return app(ShareService::class)->can($user, $this, $ability);
    }
}
