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
        $role = UserRole::from($user->role);

        // Président, DGS et Admin voient tout
        if ($role->atLeast(UserRole::DGS)) {
            return $query;
        }

        // Rôles subordonnés pour l'héritage hiérarchique des rôles
        $userLevel = $role->level();
        $subordinateRoles = array_values(array_filter(
            UserRole::values(),
            fn (string $r) => UserRole::from($r)->level() > $userLevel
        ));

        // Départements directs de l'utilisateur
        $deptIds = $user->departments()->pluck('departments.id')->toArray();

        // Départements enfants des nœuds où l'utilisateur est responsable
        // (héritage récursif descendant — is_manager=true requis)
        $managedDeptIds = $user->departments()
            ->wherePivot('is_manager', true)
            ->pluck('departments.id')
            ->toArray();

        $childDeptIds = $this->resolveChildDeptIds($managedDeptIds);

        // Tous les départements donnant accès : directs + enfants hérités
        $allDeptIds = array_unique(array_merge($deptIds, $childDeptIds));

        return $query->where(function ($q) use ($user, $subordinateRoles, $allDeptIds) {
            $q
                // 1. Albums publics
                ->where('visibility', 'public')

                // 2. Albums privés créés par l'utilisateur
                ->orWhere(function ($q2) use ($user) {
                    $q2->where('visibility', 'private')
                        ->where('created_by', $user->id);
                })

                // 3. Albums restreints accessibles
                ->orWhere(function ($q2) use ($user, $subordinateRoles, $allDeptIds) {
                    $q2->where('visibility', 'restricted')
                        ->where(function ($q3) use ($user, $subordinateRoles, $allDeptIds) {
                            $q3
                                // Créateur de l'album
                                ->orWhere('created_by', $user->id)

                                // Délégation individuelle
                                ->orWhereHas('shares', fn ($s) => $s
                                    ->where('shared_with_type', 'user')
                                    ->where('shared_with_id', $user->id)
                                    ->where('can_view', true))

                                // Délégation nœud direct OU nœuds enfants hérités
                                ->orWhereHas('shares', fn ($s) => $s
                                    ->where('shared_with_type', 'department')
                                    ->whereIn('shared_with_id', $allDeptIds)
                                    ->where('can_view', true))

                                // Droit par rôle exact
                                ->orWhereHas('shares', fn ($s) => $s
                                    ->where('shared_with_type', 'role')
                                    ->where('shared_with_role', $user->role)
                                    ->where('can_view', true))

                                // Héritage hiérarchique des rôles
                                ->orWhereHas('shares', fn ($s) => $s
                                    ->where('shared_with_type', 'role')
                                    ->whereIn('shared_with_role', $subordinateRoles)
                                    ->where('can_view', true));
                        });
                });
        });
    }

    /**
     * Retourne récursivement tous les IDs enfants des nœuds donnés.
     * Utilisé par scopeVisibleFor pour l'héritage hiérarchique des nœuds.
     *
     * @param  array<int>  $parentIds
     * @return array<int>
     */
    private function resolveChildDeptIds(array $parentIds): array
    {
        $allChildren = [];
        $toProcess = $parentIds;

        while (! empty($toProcess)) {
            $children = \App\Models\Tenant\Department::whereIn('parent_id', $toProcess)
                ->pluck('id')
                ->toArray();

            $newChildren = array_diff($children, $allChildren, $parentIds);

            if (empty($newChildren)) {
                break;
            }

            $allChildren = array_merge($allChildren, $newChildren);
            $toProcess = $newChildren;
        }

        return $allChildren;
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
