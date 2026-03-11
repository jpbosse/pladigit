<?php

namespace App\Services;

use App\Enums\AlbumPermissionLevel;
use App\Enums\UserRole;
use App\Models\Tenant\AlbumPermission;
use App\Models\Tenant\AlbumUserPermission;
use App\Models\Tenant\MediaAlbum;
use App\Models\Tenant\User;
use Illuminate\Support\Collection;

/**
 * Résolution des droits effectifs sur un album.
 *
 * Règle de résolution (priorité décroissante) pour CHAQUE album de la chaîne :
 *   1. Permission utilisateur individuel sur cet album
 *   2. Permission service de l'utilisateur sur cet album
 *   3. Permission direction de l'utilisateur sur cet album
 *   4. Permission rôle exact de l'utilisateur sur cet album
 *   5. Si aucune trouvée sur cet album → remonte au parent
 *   6. Jusqu'à la racine. Si rien → AlbumPermissionLevel::None
 *
 * Cas spéciaux :
 *   - Admin/Président/DGS → toujours Admin, sans consulter les permissions
 *   - level=none sur un album enfant → coupe l'héritage (exclusion explicite)
 *   - level=none sur un album parent → hérité par les enfants sauf override
 */
class AlbumPermissionService
{
    /**
     * Retourne le niveau effectif d'un utilisateur sur un album.
     * Prend en compte la hiérarchie et les overrides.
     */
    public function effectiveLevel(User $user, MediaAlbum $album): AlbumPermissionLevel
    {
        // Super-admins voient et administrent tout
        if ($user->role && UserRole::from($user->role)->atLeast(UserRole::DGS)) {
            return AlbumPermissionLevel::Admin;
        }

        // Créateur de l'album → admin
        if ($album->created_by === $user->id) {
            return AlbumPermissionLevel::Admin;
        }

        // Album public → vue pour tous minimum
        if ($album->visibility === 'public') {
            $resolved = $this->resolveChain($user, $album);

            return AlbumPermissionLevel::max($resolved, AlbumPermissionLevel::View);
        }

        return $this->resolveChain($user, $album);
    }

    /**
     * Vérifie si un utilisateur peut effectuer une action sur un album.
     */
    public function can(User $user, MediaAlbum $album, AlbumPermissionLevel $required): bool
    {
        return $this->effectiveLevel($user, $album)->atLeast($required);
    }

    /** Raccourci : peut visualiser */
    public function canView(User $user, MediaAlbum $album): bool
    {
        return $this->can($user, $album, AlbumPermissionLevel::View);
    }

    /** Raccourci : peut télécharger */
    public function canDownload(User $user, MediaAlbum $album): bool
    {
        return $this->can($user, $album, AlbumPermissionLevel::Download);
    }

    /** Raccourci : peut administrer */
    public function canAdmin(User $user, MediaAlbum $album): bool
    {
        return $this->can($user, $album, AlbumPermissionLevel::Admin);
    }

    // ── Résolution interne ───────────────────────────────────────────────────

    /**
     * Remonte la chaîne album → parent → grand-parent → ... jusqu'à la racine.
     * Retourne le premier niveau trouvé (héritage descendant avec override).
     */
    private function resolveChain(User $user, MediaAlbum $album): AlbumPermissionLevel
    {
        // Construire la chaîne d'ancêtres (album courant en premier)
        $chain = $this->buildAncestorChain($album);

        // Départements de l'utilisateur (chargés une seule fois)
        $deptIds = $user->departments()->pluck('departments.id')->toArray();
        $serviceIds = $this->filterByType($deptIds, 'service');
        $directionIds = $this->filterByType($deptIds, 'direction');

        // Parcourir la chaîne du plus précis (album courant) au plus général (racine)
        foreach ($chain as $node) {
            $level = $this->resolveOnAlbum($user, $node, $serviceIds, $directionIds);

            if ($level !== null) {
                // Trouvé sur ce nœud — s'applique (même si c'est 'none')
                return $level;
            }
        }

        // Aucune permission trouvée nulle part
        return AlbumPermissionLevel::None;
    }

    /**
     * Résout la permission sur un album précis, sans remonter.
     * Retourne null si aucune permission définie sur cet album.
     *
     * @param  array<int>  $serviceIds
     * @param  array<int>  $directionIds
     */
    private function resolveOnAlbum(
        User $user,
        MediaAlbum $album,
        array $serviceIds,
        array $directionIds
    ): ?AlbumPermissionLevel {
        $albumId = $album->getKey();

        // ── 1. Permission utilisateur individuel ─────────────────────────────
        $userPerm = AlbumUserPermission::forAlbum($albumId)
            ->forUser($user->id)
            ->first();

        if ($userPerm !== null) {
            return $userPerm->level;
        }

        // ── 2. Permission service ────────────────────────────────────────────
        if (! empty($serviceIds)) {
            $servicePerm = AlbumPermission::forAlbum($albumId)
                ->where('subject_type', 'service')
                ->whereIn('subject_id', $serviceIds)
                ->orderByRaw("FIELD(level, 'admin', 'download', 'view', 'none')")
                ->first();

            if ($servicePerm !== null) {
                return $servicePerm->level;
            }
        }

        // ── 3. Permission direction ──────────────────────────────────────────
        if (! empty($directionIds)) {
            $dirPerm = AlbumPermission::forAlbum($albumId)
                ->where('subject_type', 'direction')
                ->whereIn('subject_id', $directionIds)
                ->orderByRaw("FIELD(level, 'admin', 'download', 'view', 'none')")
                ->first();

            if ($dirPerm !== null) {
                return $dirPerm->level;
            }
        }

        // ── 4. Permission rôle hiérarchique ─────────────────────────────────
        // Une permission stockée sur role_minimum = X s'applique à tous les
        // utilisateurs ayant un rôle de niveau ≤ X (X et en-dessous).
        // Ex : 'resp_service' → s'applique à resp_service ET resp_direction.
        // On cherche la permission de rôle la plus restrictive applicable
        // (niveau de rôle le plus haut parmi ceux qui couvrent l'utilisateur).
        $userRoleLevel = UserRole::tryFrom($user->role ?? '')?->level() ?? 99;

        $rolePerm = AlbumPermission::forAlbum($albumId)
            ->where('subject_type', 'role')
            ->whereNotNull('subject_role')
            ->get()
            ->filter(function (AlbumPermission $perm) use ($userRoleLevel) {
                // La permission s'applique si le rôle de l'utilisateur est ≥
                // au rôle pivot (resp_direction=4, resp_service=3, user=2)
                $pivotLevel = UserRole::tryFrom($perm->subject_role)?->level() ?? 0;

                return $userRoleLevel >= $pivotLevel;
            })
            ->sortByDesc(fn (AlbumPermission $p) => UserRole::tryFrom($p->subject_role)?->level() ?? 0
            )
            ->first();

        if ($rolePerm !== null) {
            return $rolePerm->level;
        }

        // Rien trouvé sur cet album spécifique
        return null;
    }

    /**
     * Construit la chaîne d'ancêtres depuis l'album courant jusqu'à la racine.
     * [album, parent, grand-parent, ..., racine]
     *
     * @return array<MediaAlbum>
     */
    private function buildAncestorChain(MediaAlbum $album): array
    {
        $chain = [$album];
        $current = $album;
        $visited = [$album->getKey()]; // protection contre les boucles

        while ($current->parent_id !== null) {
            if (in_array($current->parent_id, $visited)) {
                break; // sécurité — boucle détectée
            }

            $parent = MediaAlbum::find($current->parent_id);

            if ($parent === null) {
                break;
            }

            $chain[] = $parent;
            $visited[] = $parent->getKey();
            $current = $parent;
        }

        return $chain;
    }

    /**
     * Filtre une liste d'IDs de départements par type.
     *
     * @param  array<int>  $deptIds
     * @return array<int>
     */
    private function filterByType(array $deptIds, string $type): array
    {
        if (empty($deptIds)) {
            return [];
        }

        return \App\Models\Tenant\Department::whereIn('id', $deptIds)
            ->where('type', $type)
            ->pluck('id')
            ->toArray();
    }

    // ── Gestion des permissions ──────────────────────────────────────────────

    /**
     * Définit ou met à jour la permission d'un utilisateur individuel sur un album.
     */
    public function setUserPermission(
        MediaAlbum $album,
        User $user,
        AlbumPermissionLevel $level
    ): AlbumUserPermission {
        return AlbumUserPermission::updateOrCreate(
            ['album_id' => $album->getKey(), 'user_id' => $user->id],
            ['level' => $level]
        );
    }

    /**
     * Définit ou met à jour la permission d'un rôle sur un album.
     */
    public function setRolePermission(
        MediaAlbum $album,
        string $role,
        AlbumPermissionLevel $level
    ): AlbumPermission {
        return AlbumPermission::updateOrCreate(
            [
                'album_id' => $album->getKey(),
                'subject_type' => 'role',
                'subject_id' => null,
                'subject_role' => $role,
            ],
            ['level' => $level]
        );
    }

    /**
     * Définit ou met à jour la permission d'un département (direction ou service).
     */
    public function setDepartmentPermission(
        MediaAlbum $album,
        \App\Models\Tenant\Department $department,
        AlbumPermissionLevel $level
    ): AlbumPermission {
        return AlbumPermission::updateOrCreate(
            [
                'album_id' => $album->getKey(),
                'subject_type' => $department->type, // 'direction' ou 'service'
                'subject_id' => $department->id,
                'subject_role' => null,
            ],
            ['level' => $level]
        );
    }

    /**
     * Supprime toutes les permissions d'un album (lors de sa suppression).
     */
    public function revokeAll(MediaAlbum $album): void
    {
        AlbumPermission::forAlbum($album->getKey())->delete();
        AlbumUserPermission::forAlbum($album->getKey())->delete();
    }

    /**
     * Retourne toutes les permissions définies sur un album (pour l'UI admin).
     *
     * @return array{role: Collection, department: Collection, user: Collection}
     */
    public function permissionsFor(MediaAlbum $album): array
    {
        return [
            'role' => AlbumPermission::forAlbum($album->getKey())
                ->where('subject_type', 'role')
                ->get(),
            'department' => AlbumPermission::forAlbum($album->getKey())
                ->whereIn('subject_type', ['direction', 'service'])
                ->with('department')
                ->get(),
            'user' => AlbumUserPermission::forAlbum($album->getKey())
                ->with('user')
                ->get(),
        ];
    }
}
