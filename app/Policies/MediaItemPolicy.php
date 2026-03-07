<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Tenant\MediaItem;
use App\Models\Tenant\User;
use App\Services\ShareService;

class MediaItemPolicy
{
    public function __construct(private ShareService $shareService) {}

    public function before(User $user, string $ability): ?bool
    {
        $role = $user->role ? UserRole::from($user->role) : null;
        if ($role && in_array($role, [UserRole::PRESIDENT, UserRole::DGS], true)) {
            return true;
        }

        return null;
    }

    /**
     * Résolution : droits média > droits album > refus.
     */
    public function view(User $user, MediaItem $item): bool
    {
        // Droits directs sur le média
        if ($item->shares()->exists()) {
            return $item->userCan($user, 'can_view');
        }
        // Héritage album
        $album = $item->album;
        if (! $album) {
            return false;
        }
        if ($album->visibility === 'public') {
            return true;
        }
        if ($album->visibility === 'private') {
            return $album->created_by === $user->id;
        }

        return $album->userCan($user, 'can_view');
    }

    public function download(User $user, MediaItem $item): bool
    {
        if ($item->shares()->exists()) {
            return $item->userCan($user, 'can_download');
        }
        $album = $item->album;
        if (! $album) {
            return false;
        }
        if ($album->visibility === 'private') {
            return $album->created_by === $user->id;
        }

        return $album->userCan($user, 'can_download');
    }

    public function manage(User $user, MediaItem $item): bool
    {
        $album = $item->album;
        if (! $album) {
            return false;
        }
        // Peut gérer les partages du média si can_manage sur l'album
        if ($album->created_by === $user->id) {
            return true;
        }

        return $album->userCan($user, 'can_manage');
    }
}
