<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Tenant\MediaAlbum;
use App\Models\Tenant\User;

class MediaAlbumPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        // Admin, Président et DGS : accès total à tous les albums
        $role = $user->role ? UserRole::from($user->role) : null;
        if ($role && in_array($role, [UserRole::ADMIN, UserRole::PRESIDENT, UserRole::DGS], true)) {
            return true;
        }

        return null;
    }

    public function view(User $user, MediaAlbum $album): bool
    {
        if ($album->visibility === 'public') {
            return true;
        }
        if ($album->visibility === 'private') {
            return $album->created_by === $user->id;
        }

        return $album->userCan($user, 'can_view');
    }

    public function download(User $user, MediaAlbum $album): bool
    {
        if ($album->visibility === 'private') {
            return $album->created_by === $user->id;
        }

        return $album->userCan($user, 'can_download');
    }

    public function manage(User $user, MediaAlbum $album): bool
    {
        // Créateur de l'album → toujours admin (before() ne couvre pas ce cas
        // pour les rôles non bypassés comme resp_service ou user)
        if ($album->created_by === $user->id) {
            return true;
        }

        // Resp. Direction et au-dessus → déléguer au AlbumPermissionService
        // (Admin/Président/DGS sont déjà interceptés par before())
        $role = UserRole::from($user->role);

        if (! $role->atLeast(UserRole::RESP_DIRECTION)) {
            return false;
        }

        // Pour resp_direction : vérifier la permission effective (niveau Admin requis)
        return $album->canAdmin($user);
    }

    public function create(User $user): bool
    {
        return $user->role && UserRole::from($user->role)->atLeast(UserRole::RESP_SERVICE);
    }

    public function delete(User $user, MediaAlbum $album): bool
    {
        if ($album->created_by === $user->id) {
            return true;
        }

        $role = UserRole::from($user->role);

        return in_array($role, [UserRole::PRESIDENT, UserRole::DGS], true);
    }
}
