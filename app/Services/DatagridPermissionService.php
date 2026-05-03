<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Tenant\DatagridPermission;
use App\Models\Tenant\DatagridTable;
use App\Models\Tenant\DatagridUserPermission;
use App\Models\Tenant\Department;
use App\Models\Tenant\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Résolution des droits effectifs d'un utilisateur sur une grille DataGrid.
 *
 * Règle de résolution (priorité décroissante) :
 *   1. Admin / Président / DGS → tous les droits, sans consulter les tables
 *   2. Permission individuelle utilisateur (datagrid_user_permissions)
 *      — denied=true bloque définitivement
 *   3. Permission départementale, remontée hiérarchique via parent_id
 *      — denied=true sur n'importe quelle règle applicable bloque
 *      — plusieurs règles applicables → union la plus permissive (OR sur chaque flag)
 *   4. Permission par rôle hiérarchique
 *      — règle applicable si userLevel <= pivotLevel (même sémantique Albums)
 *      — denied=true bloque ; sinon union la plus permissive
 *   5. Aucune règle → tout refusé
 *
 * Cache Redis : clé par organisation + utilisateur + table (tag par table).
 * TTL 3600 s. Invalidation immédiate à chaque modification de règle.
 */
class DatagridPermissionService
{
    private const CACHE_TTL = 3600;

    // ── API publique — droits sur la table entière ───────────────────────────

    public function canRead(User $user, DatagridTable $table): bool
    {
        return $this->effectivePermissions($user, $table)['can_read'];
    }

    public function canWrite(User $user, DatagridTable $table): bool
    {
        return $this->effectivePermissions($user, $table)['can_write'];
    }

    public function canDelete(User $user, DatagridTable $table): bool
    {
        return $this->effectivePermissions($user, $table)['can_delete'];
    }

    public function canExport(User $user, DatagridTable $table): bool
    {
        return $this->effectivePermissions($user, $table)['can_export'];
    }

    /**
     * Droits effectifs complets, avec cache Redis.
     *
     * @return array{can_read: bool, can_write: bool, can_delete: bool, can_export: bool}
     */
    public function effectivePermissions(User $user, DatagridTable $table): array
    {
        $key = $this->cacheKey($user, $table);

        return Cache::tags($this->tableCacheTags($table))
            ->remember($key, self::CACHE_TTL, fn () => $this->resolve($user, $table));
    }

    // ── Gestion des règles ───────────────────────────────────────────────────

    /**
     * Définit ou met à jour la règle individuelle d'un utilisateur sur une table.
     */
    public function setUserPermission(
        DatagridTable $table,
        User $user,
        bool $canRead,
        bool $canWrite,
        bool $canDelete,
        bool $canExport,
        bool $denied = false,
        ?string $columnName = null
    ): DatagridUserPermission {
        $perm = DatagridUserPermission::updateOrCreate(
            [
                'datagrid_table_id' => $table->getKey(),
                'user_id' => $user->getKey(),
                'column_name' => $columnName,
            ],
            [
                'can_read' => $canRead,
                'can_write' => $canWrite,
                'can_delete' => $canDelete,
                'can_export' => $canExport,
                'denied' => $denied,
            ]
        );

        $this->invalidateCacheForUser($user, $table);

        return $perm;
    }

    /**
     * Définit ou met à jour la règle d'un rôle sur une table.
     */
    public function setRolePermission(
        DatagridTable $table,
        string $role,
        bool $canRead,
        bool $canWrite,
        bool $canDelete,
        bool $canExport,
        bool $denied = false,
        ?string $columnName = null
    ): DatagridPermission {
        $perm = DatagridPermission::updateOrCreate(
            [
                'datagrid_table_id' => $table->getKey(),
                'subject_type' => 'role',
                'subject_id' => null,
                'subject_role' => $role,
                'column_name' => $columnName,
            ],
            [
                'can_read' => $canRead,
                'can_write' => $canWrite,
                'can_delete' => $canDelete,
                'can_export' => $canExport,
                'denied' => $denied,
            ]
        );

        $this->invalidateCacheForTable($table);

        return $perm;
    }

    /**
     * Définit ou met à jour la règle d'un département sur une table.
     */
    public function setDepartmentPermission(
        DatagridTable $table,
        Department $department,
        bool $canRead,
        bool $canWrite,
        bool $canDelete,
        bool $canExport,
        bool $denied = false,
        ?string $columnName = null
    ): DatagridPermission {
        $perm = DatagridPermission::updateOrCreate(
            [
                'datagrid_table_id' => $table->getKey(),
                'subject_type' => 'department',
                'subject_id' => $department->getKey(),
                'subject_role' => null,
                'column_name' => $columnName,
            ],
            [
                'can_read' => $canRead,
                'can_write' => $canWrite,
                'can_delete' => $canDelete,
                'can_export' => $canExport,
                'denied' => $denied,
            ]
        );

        $this->invalidateCacheForTable($table);

        return $perm;
    }

    /**
     * Supprime toutes les règles d'une table (lors de sa suppression logique).
     */
    public function revokeAll(DatagridTable $table): void
    {
        DatagridPermission::forTable($table->getKey())->delete();
        DatagridUserPermission::forTable($table->getKey())->delete();
        $this->invalidateCacheForTable($table);
    }

    /**
     * Toutes les règles définies sur une table, pour l'UI admin.
     *
     * @return array{role: Collection, department: Collection, user: Collection}
     */
    public function permissionsFor(DatagridTable $table): array
    {
        return [
            'role' => DatagridPermission::forTable($table->getKey())
                ->where('subject_type', 'role')
                ->whereNull('column_name')
                ->get(),
            'department' => DatagridPermission::forTable($table->getKey())
                ->where('subject_type', 'department')
                ->whereNull('column_name')
                ->with('department')
                ->get(),
            'user' => DatagridUserPermission::forTable($table->getKey())
                ->whereNull('column_name')
                ->with('user')
                ->get(),
        ];
    }

    // ── Invalidation du cache ────────────────────────────────────────────────

    /** Invalide le cache d'un utilisateur précis sur une table. */
    public function invalidateCacheForUser(User $user, DatagridTable $table): void
    {
        Cache::tags($this->tableCacheTags($table))
            ->forget($this->cacheKey($user, $table));
    }

    /** Invalide le cache de tous les utilisateurs sur une table (modification de règle de groupe). */
    public function invalidateCacheForTable(DatagridTable $table): void
    {
        Cache::tags($this->tableCacheTags($table))->flush();
    }

    // ── Résolution interne ───────────────────────────────────────────────────

    /**
     * Calcule les droits effectifs sans cache.
     *
     * @return array{can_read: bool, can_write: bool, can_delete: bool, can_export: bool}
     */
    private function resolve(User $user, DatagridTable $table): array
    {
        // Superadmins voient et administrent tout
        if (UserRole::tryFrom($user->role ?? '')?->atLeast(UserRole::DGS)) {
            return $this->allGranted();
        }

        $tableId = $table->getKey();

        // ── 1. Permission individuelle utilisateur ───────────────────────────
        $userPerm = DatagridUserPermission::forTable($tableId)
            ->forUser($user->getKey())
            ->forColumn(null)
            ->first();

        if ($userPerm !== null) {
            return $userPerm->denied ? $this->allDenied() : $this->extractFlags($userPerm);
        }

        // ── 2. Permission départementale (chaîne hiérarchique) ───────────────
        $deptIds = $user->departments()->pluck('departments.id')->toArray();

        if (! empty($deptIds)) {
            $ancestorIds = $this->buildDeptAncestorIds($deptIds);

            /** @var Collection<int, DatagridPermission> $deptPerms */
            $deptPerms = DatagridPermission::forTable($tableId)
                ->forColumn(null)
                ->where('subject_type', 'department')
                ->whereIn('subject_id', $ancestorIds)
                ->get();

            if ($deptPerms->isNotEmpty()) {
                if ($deptPerms->contains('denied', true)) {
                    return $this->allDenied();
                }

                return $this->mergePermissive($deptPerms);
            }
        }

        // ── 3. Permission par rôle hiérarchique ──────────────────────────────
        $userRoleLevel = UserRole::tryFrom($user->role ?? '')?->level() ?? 99;

        /** @var Collection<int, DatagridPermission> $allRolePerms */
        $allRolePerms = DatagridPermission::forTable($tableId)
            ->forColumn(null)
            ->where('subject_type', 'role')
            ->whereNotNull('subject_role')
            ->get();

        // Sémantique : règle applicable si userLevel <= pivotLevel (même convention Albums)
        $rolePerms = $allRolePerms->filter(function (DatagridPermission $perm) use ($userRoleLevel) {
            $pivotLevel = UserRole::tryFrom($perm->subject_role ?? '')?->level() ?? 0;

            return $userRoleLevel <= $pivotLevel;
        });

        if ($rolePerms->isNotEmpty()) {
            if ($rolePerms->contains('denied', true)) {
                return $this->allDenied();
            }

            return $this->mergePermissive($rolePerms);
        }

        return $this->allDenied();
    }

    /**
     * Construit la liste des IDs de départements + tous leurs ancêtres.
     * Protège contre les boucles de hiérarchie corrompue.
     *
     * @param  array<int>  $deptIds
     * @return array<int>
     */
    private function buildDeptAncestorIds(array $deptIds): array
    {
        $allIds = $deptIds;
        $toProcess = $deptIds;
        $visited = array_flip($deptIds); // O(1) lookup

        while (! empty($toProcess)) {
            $parents = Department::whereIn('id', $toProcess)
                ->whereNotNull('parent_id')
                ->pluck('parent_id')
                ->toArray();

            $newParents = [];
            foreach ($parents as $parentId) {
                if (! isset($visited[$parentId])) {
                    $visited[$parentId] = true;
                    $newParents[] = $parentId;
                    $allIds[] = $parentId;
                }
            }

            $toProcess = $newParents;
        }

        return array_unique($allIds);
    }

    /**
     * Union la plus permissive de plusieurs règles (OR sur chaque flag).
     *
     * @param  Collection<int, DatagridPermission>|Collection<int, DatagridUserPermission>  $perms
     * @return array{can_read: bool, can_write: bool, can_delete: bool, can_export: bool}
     */
    private function mergePermissive(Collection $perms): array
    {
        return [
            'can_read' => $perms->contains('can_read', true),
            'can_write' => $perms->contains('can_write', true),
            'can_delete' => $perms->contains('can_delete', true),
            'can_export' => $perms->contains('can_export', true),
        ];
    }

    /**
     * @return array{can_read: bool, can_write: bool, can_delete: bool, can_export: bool}
     */
    private function extractFlags(DatagridUserPermission|DatagridPermission $perm): array
    {
        return [
            'can_read' => (bool) $perm->can_read,
            'can_write' => (bool) $perm->can_write,
            'can_delete' => (bool) $perm->can_delete,
            'can_export' => (bool) $perm->can_export,
        ];
    }

    /** @return array{can_read: bool, can_write: bool, can_delete: bool, can_export: bool} */
    private function allGranted(): array
    {
        return ['can_read' => true, 'can_write' => true, 'can_delete' => true, 'can_export' => true];
    }

    /** @return array{can_read: bool, can_write: bool, can_delete: bool, can_export: bool} */
    private function allDenied(): array
    {
        return ['can_read' => false, 'can_write' => false, 'can_delete' => false, 'can_export' => false];
    }

    // ── Clés de cache ────────────────────────────────────────────────────────

    private function cacheKey(User $user, DatagridTable $table): string
    {
        $org = app(TenantManager::class)->current()->slug ?? 'default';

        return "datagrid_perm:{$org}:{$user->getKey()}:{$table->getKey()}";
    }

    /**
     * Tags Redis pour la table — permet d'invalider tous les utilisateurs d'un coup.
     *
     * @return array<string>
     */
    private function tableCacheTags(DatagridTable $table): array
    {
        $org = app(TenantManager::class)->current()->slug ?? 'default';

        return ["datagrid:{$org}:{$table->getKey()}"];
    }
}
