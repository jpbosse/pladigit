<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Tenant\MediaAlbum;
use App\Models\Tenant\User;

class MediaAlbumPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->role && UserRole::from($user->role)->atLeast(UserRole::DGS)) {
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
        return $user->role && UserRole::from($user->role)->atLeast(UserRole::RespService);
    }

    public function delete(User $user, MediaAlbum $album): bool
    {
        return $album->created_by === $user->id
            || UserRole::from($user->role)->atLeast(UserRole::DGS);
    }
}
