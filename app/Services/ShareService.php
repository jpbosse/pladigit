<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Tenant\Share;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Service de résolution des droits de partage.
 *
 * Logique de résolution (par ordre de priorité) :
 *   1. Override utilisateur individuel
 *   2. Override département (un des départements de l'utilisateur)
 *   3. Droit par rôle exact
 *   4. Héritage hiérarchique — si un rôle de niveau inférieur (moins de droits)
 *      a can_view, les rôles supérieurs (plus de droits) héritent du droit.
 *      Ex : resp_service a can_view → resp_direction le voit aussi.
 */
class ShareService
{
    /**
     * Retourne l'alias morphMap ou le FQCN si non enregistré.
     */
    private function morphType(Model $object): string
    {
        $class = get_class($object);
        $map = array_flip(Relation::morphMap());

        return $map[$class] ?? $class;
    }

    /**
     * Vérifie si un utilisateur a un droit sur un objet.
     *
     * @param  'can_view'|'can_download'|'can_edit'|'can_manage'  $ability
     */
    public function can(User $user, Model $object, string $ability): bool
    {
        $type = $this->morphType($object);
        $id = $object->getKey();

        // 1. Override utilisateur individuel
        $userShare = Share::forModel($type, $id)
            ->forUser($user->id)
            ->first();

        if ($userShare !== null) {
            return (bool) $userShare->$ability;
        }

        // 2. Override département
        $deptIds = $user->departments()->pluck('departments.id')->toArray();

        if (! empty($deptIds)) {
            $deptShare = Share::forModel($type, $id)
                ->where('shared_with_type', 'department')
                ->whereIn('shared_with_id', $deptIds)
                ->orderByDesc($ability)
                ->first();

            if ($deptShare !== null) {
                return (bool) $deptShare->$ability;
            }
        }

        // 3. Droit par rôle exact
        $roleShare = Share::forModel($type, $id)
            ->forRole($user->role)
            ->first();

        if ($roleShare !== null) {
            return (bool) $roleShare->$ability;
        }

        // 4. Héritage hiérarchique
        // Un rôle de niveau N hérite des droits accordés aux rôles de niveau > N
        // (moins de droits dans la hiérarchie).
        // Ex : resp_direction (4) hérite de resp_service (5) et user (6).
        $userLevel = UserRole::from($user->role)->level();

        $subordinateRoles = array_values(array_filter(
            UserRole::values(),
            fn (string $r) => UserRole::from($r)->level() > $userLevel
        ));

        if (! empty($subordinateRoles)) {
            $inheritedShare = Share::forModel($type, $id)
                ->where('shared_with_type', 'role')
                ->whereIn('shared_with_role', $subordinateRoles)
                ->where($ability, true)
                ->first();

            if ($inheritedShare !== null) {
                return true;
            }
        }

        // 5. Héritage hiérarchique des nœuds organisationnels
        // Un responsable (is_manager=true) d'un nœud hérite des droits accordés
        // à tous ses nœuds enfants, petits-enfants, etc. (récursivement).
        // Ex : responsable du Pôle Technique → voit les albums délégués à la
        // Direction DST (enfant) et au Service Voirie (petit-enfant).
        $managedDeptIds = $user->departments()
            ->wherePivot('is_manager', true)
            ->pluck('departments.id')
            ->toArray();

        if (! empty($managedDeptIds)) {
            $allChildIds = $this->getAllChildDeptIds($managedDeptIds);

            if (! empty($allChildIds)) {
                $inheritedDeptShare = Share::forModel($type, $id)
                    ->where('shared_with_type', 'department')
                    ->whereIn('shared_with_id', $allChildIds)
                    ->where($ability, true)
                    ->first();

                if ($inheritedDeptShare !== null) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Retourne récursivement tous les IDs des nœuds enfants des nœuds donnés.
     * Ne retourne que les enfants — pas les nœuds de départ eux-mêmes
     * (déjà couverts par l'étape 2).
     *
     * @param  array<int>  $parentIds
     * @return array<int>
     */
    private function getAllChildDeptIds(array $parentIds): array
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

    /**
     * Retourne tous les partages d'un objet, groupés par type.
     */
    public function sharesFor(Model $object): \Illuminate\Support\Collection
    {
        return Share::where('shareable_type', $this->morphType($object))
            ->where('shareable_id', $object->getKey())
            ->with(['sharedWithUser', 'sharedWithDepartment'])
            ->get();
    }

    /**
     * Crée ou met à jour un partage.
     */
    public function upsert(
        Model $object,
        string $withType,
        ?int $withId,
        ?string $withRole,
        array $abilities,
        ?int $sharedBy = null
    ): Share {
        return Share::updateOrCreate(
            [
                'shareable_type' => $this->morphType($object),
                'shareable_id' => $object->getKey(),
                'shared_with_type' => $withType,
                'shared_with_id' => $withId,
                'shared_with_role' => $withRole,
            ],
            array_merge($abilities, ['shared_by' => $sharedBy])
        );
    }

    /**
     * Supprime un partage.
     */
    public function revoke(Share $share): void
    {
        $share->delete();
    }

    /**
     * Supprime tous les partages d'un objet (ex: à la suppression).
     */
    public function revokeAll(Model $object): void
    {
        Share::where('shareable_type', $this->morphType($object))
            ->where('shareable_id', $object->getKey())
            ->delete();
    }
}
