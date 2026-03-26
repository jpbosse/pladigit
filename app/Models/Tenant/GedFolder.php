<?php

namespace App\Models\Tenant;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Dossier GED d'un tenant.
 *
 * Hiérarchie illimitée via parent_id récursif.
 * Visibilité : is_private = true → visible uniquement par le créateur + Admin/DGS.
 */
class GedFolder extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        'name',
        'slug',
        'path',
        'parent_id',
        'is_private',
        'created_by',
    ];

    protected $casts = [
        'is_private' => 'bool',
        'deleted_at' => 'datetime',
    ];

    // ── Relations ────────────────────────────────────────────

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<GedFolder, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(GedFolder::class, 'parent_id');
    }

    /** @return HasMany<GedFolder, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(GedFolder::class, 'parent_id')->orderBy('name');
    }

    /** Tous les descendants (récursif eager-load). */
    public function allChildren(): HasMany
    {
        return $this->children()->with('allChildren');
    }

    /** @return HasMany<GedDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(GedDocument::class, 'folder_id');
    }

    // ── Scopes ───────────────────────────────────────────────

    /** Dossiers racine uniquement. */
    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Dossiers visibles pour un utilisateur :
     *   - Admin / Président / DGS → tout
     *   - is_private = false → tout le monde
     *   - is_private = true → créateur uniquement
     */
    public function scopeVisibleFor($query, User $user)
    {
        if ($user->role && UserRole::from($user->role)->atLeast(UserRole::DGS)) {
            return $query;
        }

        return $query->where(function ($q) use ($user) {
            $q->where('is_private', false)
                ->orWhere('created_by', $user->id);
        });
    }

    // ── Helpers arborescence ─────────────────────────────────

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

    /**
     * Vérifie si ce dossier est un ancêtre de $folder.
     * Utilisé pour la protection anti-boucle circulaire.
     */
    public function isAncestorOf(GedFolder $folder): bool
    {
        return in_array($folder->id, $this->descendantIds(), true);
    }

    /**
     * Retourne tous les ancêtres du plus lointain au plus proche.
     * Utile pour le fil d'Ariane et buildPath().
     *
     * @return array<GedFolder>
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
     * Reconstruit le path complet depuis la racine.
     * Ex: /RH/Contrats/2024
     */
    public function buildPath(): string
    {
        $parts = array_map(fn (GedFolder $f) => $f->slug, $this->ancestors());
        $parts[] = $this->slug;

        return '/'.implode('/', $parts);
    }

    // ── Helpers ──────────────────────────────────────────────

    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    /**
     * Génère un slug unique parmi les enfants du même parent.
     * Suffixe -2, -3… si conflit.
     */
    public static function uniqueSlug(string $name, ?int $parentId, ?int $exceptId = null): string
    {
        $base = \Illuminate\Support\Str::slug($name) ?: 'dossier';
        $slug = $base;
        $i = 2;

        while (
            static::where('slug', $slug)
                ->where('parent_id', $parentId)
                ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
                ->whereNull('deleted_at')
                ->exists()
        ) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
