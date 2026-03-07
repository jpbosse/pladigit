<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Tenant\MediaAlbum;
use App\Models\Tenant\User;

class MediaAlbumPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        // Président et DGS : accès total à tous les albums
        // Admin : accès via shares comme les autres (peut être exclu de certains albums RH/compta)
        $role = $user->role ? UserRole::from($user->role) : null;
        if ($role && in_array($role, [UserRole::PRESIDENT, UserRole::DGS], true)) {
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
        if ($album->created_by === $user->id) {
            return true;
        }

        return $album->userCan($user, 'can_manage');
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
