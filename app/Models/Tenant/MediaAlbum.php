<?php

namespace App\Models\Tenant;

use App\Enums\AlbumPermissionLevel;
use App\Enums\UserRole;
use App\Services\AlbumPermissionService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Album photo d'un tenant.
 *
 * Hiérarchie : un album peut avoir un parent_id → sous-album.
 * Les droits s'héritent du parent sauf override explicite.
 *
 * Relations :
 *   $album->creator()          → User qui a créé l'album
 *   $album->parent()           → Album parent (null si racine)
 *   $album->children()         → Sous-albums directs
 *   $album->items()            → MediaItem de cet album
 *   $album->permissions()      → AlbumPermission (rôle/direction/service)
 *   $album->userPermissions()  → AlbumUserPermission (individuel)
 *   $album->shareLinks()       → Liens de partage temporaires
 */
class MediaAlbum extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        'parent_id',
        'created_by',
        'name',
        'description',
        'cover_path',
        'nas_path',
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

    /** @return BelongsTo<MediaAlbum, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(MediaAlbum::class, 'parent_id');
    }

    /** @return HasMany<MediaAlbum, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(MediaAlbum::class, 'parent_id');
    }

    /** @return HasMany<MediaItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(MediaItem::class, 'album_id');
    }

    /** @return HasMany<AlbumPermission, $this> */
    public function permissions(): HasMany
    {
        return $this->hasMany(AlbumPermission::class, 'album_id');
    }

    /** @return HasMany<AlbumUserPermission, $this> */
    public function userPermissions(): HasMany
    {
        return $this->hasMany(AlbumUserPermission::class, 'album_id');
    }

    /** @return HasMany<MediaShareLink, $this> */
    public function shareLinks(): HasMany
    {
        return $this->hasMany(MediaShareLink::class, 'album_id');
    }

    // ── Scopes ───────────────────────────────────────────────

    /** Albums racine uniquement (sans parent) */
    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopePublic($query)
    {
        return $query->where('visibility', 'public');
    }

    /**
     * Albums visibles pour un utilisateur donné.
     * Délègue au AlbumPermissionService pour la résolution complète.
     */
    public function scopeVisibleFor($query, User $user)
    {
        // Admin/Président/DGS voient tout
        if ($user->role && UserRole::from($user->role)->atLeast(UserRole::DGS)) {
            return $query;
        }

        $service = app(AlbumPermissionService::class);
        $allAlbums = static::all(['id', 'parent_id', 'created_by', 'visibility']);

        $accessibleIds = $allAlbums
            ->filter(fn (MediaAlbum $album) => $service->canView($user, $album))
            ->pluck('id')
            ->toArray();

        return $query->whereIn('id', $accessibleIds);
    }

    // ── Helpers droits ───────────────────────────────────────

    public function effectiveLevel(User $user): AlbumPermissionLevel
    {
        return app(AlbumPermissionService::class)->effectiveLevel($user, $this);
    }

    public function canView(User $user): bool
    {
        return app(AlbumPermissionService::class)->canView($user, $this);
    }

    public function canDownload(User $user): bool
    {
        return app(AlbumPermissionService::class)->canDownload($user, $this);
    }

    public function canUpload(User $user): bool
    {
        return app(AlbumPermissionService::class)->canUpload($user, $this);
    }

    public function canAdmin(User $user): bool
    {
        return app(AlbumPermissionService::class)->canAdmin($user, $this);
    }

    /**
     * Compatibilité avec l'ancien code utilisant userCan().
     *
     * @param  'can_view'|'can_download'|'can_upload'|'can_edit'|'can_manage'  $ability
     */
    public function userCan(User $user, string $ability): bool
    {
        return match ($ability) {
            'can_view' => $this->canView($user),
            'can_download' => $this->canDownload($user),
            'can_upload' => $this->canUpload($user),
            'can_edit',
            'can_manage' => $this->canAdmin($user),
            default => false,
        };
    }

    // ── Helpers arborescence ─────────────────────────────────

    public function isChild(): bool
    {
        return $this->parent_id !== null;
    }

    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    /**
     * Retourne tous les ancêtres du plus lointain au plus proche.
     * Utile pour le fil d'Ariane.
     *
     * @return array<MediaAlbum>
     */
    public function ancestors(): array
    {
        $ancestors = [];
        $current = $this;
        $visited = [$this->getKey()];

        while ($current->parent_id !== null) {
            if (in_array($current->parent_id, $visited)) {
                break;
            }

            $parent = static::find($current->parent_id);

            if ($parent === null) {
                break;
            }

            array_unshift($ancestors, $parent);
            $visited[] = $parent->getKey();
            $current = $parent;
        }

        return $ancestors;
    }

    /**
     * Retourne tous les IDs descendants récursivement.
     * Utilisé pour interdire les boucles circulaires parent_id.
     *
     * @return array<int>
     */
    public function descendantIds(): array
    {
        $ids = [];
        $toProcess = [$this->getKey()];

        while (count($toProcess) > 0) {
            $children = static::whereIn('parent_id', $toProcess)
                ->pluck('id')
                ->toArray();

            $newChildren = array_diff($children, $ids);

            if ($newChildren === []) {
                break;
            }

            $ids = array_merge($ids, $newChildren);
            $toProcess = $newChildren;
        }

        return $ids;
    }

    // ── Helpers affichage ────────────────────────────────────

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
}
