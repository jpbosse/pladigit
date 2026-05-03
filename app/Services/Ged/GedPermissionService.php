<?php

namespace App\Services\Ged;

use App\Enums\GedPermissionLevel;
use App\Enums\UserRole;
use App\Models\Tenant\Department;
use App\Models\Tenant\GedDocument;
use App\Models\Tenant\GedFolder;
use App\Models\Tenant\GedFolderPermission;
use App\Models\Tenant\GedFolderUserPermission;
use App\Models\Tenant\User;
use Illuminate\Support\Collection;

/**
 * Résolution des droits effectifs sur un dossier GED.
 *
 * Règle de résolution (priorité décroissante) pour CHAQUE dossier de la chaîne :
 *   1. Permission utilisateur individuel sur ce dossier
 *   2. Permission service de l'utilisateur sur ce dossier
 *   3. Permission direction de l'utilisateur sur ce dossier
 *   4. Permission rôle exact de l'utilisateur sur ce dossier
 *   5. Si aucune trouvée sur ce dossier → remonte au parent
 *   6. Jusqu'à la racine. Si rien → GedPermissionLevel::None
 *
 * Cas spéciaux :
 *   - Admin/Président/DGS → toujours Admin, sans consulter les permissions
 *   - Créateur du dossier → toujours Admin
 *   - level=none sur un dossier enfant → coupe l'héritage (exclusion explicite)
 */
class GedPermissionService
{
    /**
     * Retourne le niveau effectif d'un utilisateur sur un dossier.
     *
     * @param  Collection<int, GedFolder>|null  $allFolders  Collection pré-chargée (évite N+1 dans scopeVisibleFor).
     */
    public function effectiveLevel(User $user, GedFolder $folder, ?Collection $allFolders = null): GedPermissionLevel
    {
        // Super-admins administrent tout
        if ($user->role && UserRole::from($user->role)->atLeast(UserRole::DGS)) {
            return GedPermissionLevel::Admin;
        }

        // Créateur du dossier → admin
        if ($folder->created_by === $user->id) {
            return GedPermissionLevel::Admin;
        }

        return $this->resolveChain($user, $folder, $allFolders);
    }

    /**
     * Retourne le niveau effectif d'un utilisateur sur le dossier d'un document.
     *
     * @param  Collection<int, GedFolder>|null  $allFolders
     */
    public function effectiveLevelForDocument(User $user, GedDocument $document, ?Collection $allFolders = null): GedPermissionLevel
    {
        $folder = $document->folder;

        if ($folder === null) {
            return GedPermissionLevel::None;
        }

        // Créateur du document → upload minimum (peut re-upload, télécharger)
        if ($document->created_by === $user->id) {
            $folderLevel = $this->effectiveLevel($user, $folder, $allFolders);

            return GedPermissionLevel::max($folderLevel, GedPermissionLevel::Upload);
        }

        return $this->effectiveLevel($user, $folder, $allFolders);
    }

    /**
     * Vérifie si un utilisateur peut effectuer une action sur un dossier.
     *
     * @param  Collection<int, GedFolder>|null  $allFolders
     */
    public function can(User $user, GedFolder $folder, GedPermissionLevel $required, ?Collection $allFolders = null): bool
    {
        return $this->effectiveLevel($user, $folder, $allFolders)->atLeast($required);
    }

    /** Raccourci : peut visualiser le dossier */
    public function canView(User $user, GedFolder $folder, ?Collection $allFolders = null): bool
    {
        return $this->can($user, $folder, GedPermissionLevel::View, $allFolders);
    }

    /** Raccourci : peut télécharger depuis le dossier */
    public function canDownload(User $user, GedFolder $folder, ?Collection $allFolders = null): bool
    {
        return $this->can($user, $folder, GedPermissionLevel::Download, $allFolders);
    }

    /** Raccourci : peut uploader dans le dossier */
    public function canUpload(User $user, GedFolder $folder, ?Collection $allFolders = null): bool
    {
        return $this->can($user, $folder, GedPermissionLevel::Upload, $allFolders);
    }

    /** Raccourci : peut administrer le dossier */
    public function canAdmin(User $user, GedFolder $folder, ?Collection $allFolders = null): bool
    {
        return $this->can($user, $folder, GedPermissionLevel::Admin, $allFolders);
    }

    // ── Résolution interne ───────────────────────────────────────────────────

    /**
     * Remonte la chaîne dossier → parent → grand-parent → ... jusqu'à la racine.
     * Retourne le premier niveau trouvé (héritage descendant avec override).
     */
    private function resolveChain(User $user, GedFolder $folder, ?Collection $allFolders = null): GedPermissionLevel
    {
        $chain = $this->buildAncestorChain($folder, $allFolders);

        // Départements de l'utilisateur (chargés une seule fois)
        $deptIds = $user->departments()->pluck('departments.id')->toArray();
        $serviceIds = $this->filterByType($deptIds, 'service');
        $directionIds = $this->filterByType($deptIds, 'direction');

        foreach ($chain as $node) {
            $level = $this->resolveOnFolder($user, $node, $serviceIds, $directionIds);

            if ($level !== null) {
                return $level;
            }
        }

        return GedPermissionLevel::None;
    }

    /**
     * Résout la permission sur un dossier précis, sans remonter.
     * Retourne null si aucune permission définie sur ce dossier.
     *
     * @param  array<int>  $serviceIds
     * @param  array<int>  $directionIds
     */
    private function resolveOnFolder(
        User $user,
        GedFolder $folder,
        array $serviceIds,
        array $directionIds
    ): ?GedPermissionLevel {
        $folderId = $folder->getKey();

        // ── 1. Permission utilisateur individuel ─────────────────────────────
        if ($folder->relationLoaded('userPermissions')) {
            $userPerm = $folder->userPermissions->firstWhere('user_id', $user->id);
        } else {
            $userPerm = GedFolderUserPermission::forFolder($folderId)
                ->forUser($user->id)
                ->first();
        }

        if ($userPerm !== null) {
            return $userPerm->level;
        }

        // ── 2. Permission service ────────────────────────────────────────────
        if (! empty($serviceIds)) {
            if ($folder->relationLoaded('permissions')) {
                $servicePerm = $folder->permissions
                    ->filter(fn (GedFolderPermission $p) => $p->subject_type === 'service'
                        && in_array($p->subject_id, $serviceIds))
                    ->sortByDesc(fn (GedFolderPermission $p) => $p->level->level())
                    ->first();
            } else {
                $servicePerm = GedFolderPermission::forFolder($folderId)
                    ->where('subject_type', 'service')
                    ->whereIn('subject_id', $serviceIds)
                    ->orderByRaw("FIELD(level, 'admin', 'upload', 'download', 'view', 'none')")
                    ->first();
            }

            if ($servicePerm !== null) {
                return $servicePerm->level;
            }
        }

        // ── 3. Permission direction ──────────────────────────────────────────
        if (! empty($directionIds)) {
            if ($folder->relationLoaded('permissions')) {
                $dirPerm = $folder->permissions
                    ->filter(fn (GedFolderPermission $p) => $p->subject_type === 'direction'
                        && in_array($p->subject_id, $directionIds))
                    ->sortByDesc(fn (GedFolderPermission $p) => $p->level->level())
                    ->first();
            } else {
                $dirPerm = GedFolderPermission::forFolder($folderId)
                    ->where('subject_type', 'direction')
                    ->whereIn('subject_id', $directionIds)
                    ->orderByRaw("FIELD(level, 'admin', 'upload', 'download', 'view', 'none')")
                    ->first();
            }

            if ($dirPerm !== null) {
                return $dirPerm->level;
            }
        }

        // ── 4. Permission rôle hiérarchique ─────────────────────────────────
        // Sémantique du pivot : "accessible à partir de ce rôle ET à tous les rôles supérieurs".
        // Ex : pivot resp_service (5) → resp_service (5) + resp_direction (4) + DGS (3) ✓
        //                             → agents (6) ✗
        // Formule : s'applique si userLevel <= pivotLevel
        $userRoleLevel = UserRole::tryFrom($user->role ?? '')?->level() ?? 99;

        if ($folder->relationLoaded('permissions')) {
            $rolePerm = $folder->permissions
                ->filter(fn (GedFolderPermission $p) => $p->subject_type === 'role'
                    && $p->subject_role !== null
                    && $userRoleLevel <= (UserRole::tryFrom($p->subject_role)?->level() ?? 0))
                ->sortByDesc(fn (GedFolderPermission $p) => $p->level->level())
                ->first();
        } else {
            $rolePerm = GedFolderPermission::forFolder($folderId)
                ->where('subject_type', 'role')
                ->whereNotNull('subject_role')
                ->get()
                ->filter(function (GedFolderPermission $perm) use ($userRoleLevel) {
                    $pivotLevel = UserRole::tryFrom($perm->subject_role)?->level() ?? 0;

                    return $userRoleLevel <= $pivotLevel;
                })
                ->sortByDesc(fn (GedFolderPermission $p) => $p->level->level())
                ->first();
        }

        if ($rolePerm !== null) {
            return $rolePerm->level;
        }

        return null;
    }

    /**
     * Construit la chaîne d'ancêtres depuis le dossier courant jusqu'à la racine.
     * [dossier, parent, grand-parent, ..., racine]
     *
     * @param  Collection<int, GedFolder>|null  $allFolders  Collection pré-chargée.
     * @return array<GedFolder>
     */
    private function buildAncestorChain(GedFolder $folder, ?Collection $allFolders = null): array
    {
        $chain = [$folder];
        $current = $folder;
        $visited = [$folder->getKey()];

        $index = $allFolders?->keyBy(fn (GedFolder $f) => $f->getKey());

        while ($current->parent_id !== null) {
            if (in_array($current->parent_id, $visited)) {
                break;
            }

            $parent = $index
                ? $index->get($current->parent_id)
                : GedFolder::find($current->parent_id);

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

        return Department::whereIn('id', $deptIds)
            ->where('type', $type)
            ->pluck('id')
            ->toArray();
    }

    // ── Gestion des permissions ──────────────────────────────────────────────

    /**
     * Définit ou met à jour la permission d'un utilisateur individuel sur un dossier.
     */
    public function setUserPermission(GedFolder $folder, User $user, GedPermissionLevel $level): GedFolderUserPermission
    {
        return GedFolderUserPermission::updateOrCreate(
            ['folder_id' => $folder->getKey(), 'user_id' => $user->id],
            ['level' => $level]
        );
    }

    /**
     * Définit ou met à jour la permission d'un rôle sur un dossier.
     */
    public function setRolePermission(GedFolder $folder, string $role, GedPermissionLevel $level): GedFolderPermission
    {
        return GedFolderPermission::updateOrCreate(
            [
                'folder_id' => $folder->getKey(),
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
        GedFolder $folder,
        Department $department,
        GedPermissionLevel $level
    ): GedFolderPermission {
        return GedFolderPermission::updateOrCreate(
            [
                'folder_id' => $folder->getKey(),
                'subject_type' => $department->type,
                'subject_id' => $department->id,
                'subject_role' => null,
            ],
            ['level' => $level]
        );
    }

    /**
     * Supprime toutes les permissions d'un dossier (lors de sa suppression).
     */
    public function revokeAll(GedFolder $folder): void
    {
        GedFolderPermission::forFolder($folder->getKey())->delete();
        GedFolderUserPermission::forFolder($folder->getKey())->delete();
    }

    /**
     * Retourne toutes les permissions définies sur un dossier (pour l'UI admin).
     *
     * @return array{role: Collection, department: Collection, user: Collection}
     */
    public function permissionsFor(GedFolder $folder): array
    {
        return [
            'role' => GedFolderPermission::forFolder($folder->getKey())
                ->where('subject_type', 'role')
                ->get(),
            'department' => GedFolderPermission::forFolder($folder->getKey())
                ->whereIn('subject_type', ['direction', 'service'])
                ->with('department')
                ->get(),
            'user' => GedFolderUserPermission::forFolder($folder->getKey())
                ->with('user')
                ->get(),
        ];
    }

    // ── Gouvernance ──────────────────────────────────────────────────────────

    /**
     * Transfère la propriété de tous les dossiers et documents d'un utilisateur
     * vers un autre. Utilisé lors d'un départ (démission, mutation).
     *
     * Retourne le nombre de ressources transférées.
     */
    public function transferOwnership(User $from, User $to): int
    {
        $folderCount = GedFolder::withTrashed()
            ->where('created_by', $from->id)
            ->update(['created_by' => $to->id]);

        $docCount = GedDocument::withTrashed()
            ->where('created_by', $from->id)
            ->update(['created_by' => $to->id]);

        // Transférer aussi les permissions individuelles
        GedFolderUserPermission::where('user_id', $from->id)
            ->update(['user_id' => $to->id]);

        return $folderCount + $docCount;
    }

    /**
     * Retourne les dossiers dont le créateur n'existe plus (compte supprimé).
     *
     * @return Collection<int, GedFolder>
     */
    public function orphanedFolders(): Collection
    {
        return GedFolder::whereNotNull('created_by')
            ->whereNotExists(function ($query) {
                $query->from('users')
                    ->whereColumn('users.id', 'ged_folders.created_by')
                    ->whereNull('users.deleted_at');
            })
            ->withCount(['documents', 'children'])
            ->orderBy('path')
            ->get();
    }

    /**
     * Retourne les documents dont le créateur n'existe plus (compte supprimé).
     *
     * @return Collection<int, GedDocument>
     */
    public function orphanedDocuments(): Collection
    {
        return GedDocument::whereNotNull('created_by')
            ->whereNotExists(function ($query) {
                $query->from('users')
                    ->whereColumn('users.id', 'ged_documents.created_by')
                    ->whereNull('users.deleted_at');
            })
            ->with('folder:id,name,path')
            ->orderBy('name')
            ->get();
    }
}
