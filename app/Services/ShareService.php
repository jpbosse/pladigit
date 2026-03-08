<?php

namespace App\Services;

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
 *   3. Droit par rôle
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

        // 3. Droit par rôle
        $roleShare = Share::forModel($type, $id)
            ->forRole($user->role)
            ->first();

        if ($roleShare !== null) {
            return (bool) $roleShare->$ability;
        }

        return false;
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
