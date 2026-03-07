<?php

namespace App\Http\Controllers\Media;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Tenant\MediaAlbum;
use App\Models\Tenant\MediaAlbumPermission;
use App\Models\Tenant\MediaAlbumUserPermission;
use App\Models\Tenant\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MediaAlbumPermissionController extends Controller
{
    /**
     * Page de gestion des droits d'un album.
     */
    public function edit(MediaAlbum $album): View
    {
        $this->authorize('manage', $album);

        // Droits par rôle indexés par role
        $rolePerms = $album->rolePermissions()
            ->get()
            ->keyBy('role');

        // Overrides utilisateur avec le user chargé
        $userPerms = $album->userPermissions()
            ->with('user')
            ->get();

        // Rôles configurables (Admin/Président/DGS sont toujours full)
        $configurableRoles = [
            UserRole::RESP_DIRECTION->value => UserRole::RESP_DIRECTION->label(),
            UserRole::RESP_SERVICE->value   => UserRole::RESP_SERVICE->label(),
            UserRole::USER->value          => UserRole::USER->label(),
        ];

        // Utilisateurs du tenant (hors Admin/Président/DGS)
        $users = User::whereNotIn('role', [
            UserRole::ADMIN->value,
            UserRole::PRESIDENT->value,
            UserRole::DGS->value,
        ])->orderBy('name')->get();

        return view('media.albums.permissions', compact(
            'album',
            'rolePerms',
            'userPerms',
            'configurableRoles',
            'users',
        ));
    }

    /**
     * Enregistre les droits par rôle.
     */
    public function updateRoles(Request $request, MediaAlbum $album): RedirectResponse
    {
        $this->authorize('manage', $album);

        $data = $request->input('roles', []);

        foreach ($data as $role => $perms) {
            // Valider que le rôle est configurable
            if (!in_array($role, [
                UserRole::RESP_DIRECTION->value,
                UserRole::RESP_SERVICE->value,
                UserRole::USER->value,
            ])) {
                continue;
            }

            MediaAlbumPermission::updateOrCreate(
                ['album_id' => $album->id, 'role' => $role],
                [
                    'can_view'     => isset($perms['can_view']),
                    'can_download' => isset($perms['can_download']),
                    'can_manage'   => isset($perms['can_manage']),
                ]
            );
        }

        // Rôles non soumis (cases toutes décochées) → mettre à false
        $submittedRoles = array_keys($data);
        $allConfigurable = [
            UserRole::RESP_DIRECTION->value,
            UserRole::RESP_SERVICE->value,
            UserRole::USER->value,
        ];

        foreach ($allConfigurable as $role) {
            if (!in_array($role, $submittedRoles)) {
                MediaAlbumPermission::updateOrCreate(
                    ['album_id' => $album->id, 'role' => $role],
                    ['can_view' => false, 'can_download' => false, 'can_manage' => false]
                );
            }
        }

        return redirect()
            ->route('media.albums.permissions.edit', $album)
            ->with('success', 'Droits par rôle enregistrés.');
    }

    /**
     * Ajoute un override utilisateur.
     */
    public function storeUser(Request $request, MediaAlbum $album): RedirectResponse
    {
        $this->authorize('manage', $album);

        $request->validate([
            'user_id' => ['required', 'integer'],
        ]);

        MediaAlbumUserPermission::updateOrCreate(
            ['album_id' => $album->id, 'user_id' => $request->integer('user_id')],
            [
                'can_view'     => $request->boolean('can_view'),
                'can_download' => $request->boolean('can_download'),
                'can_manage'   => $request->boolean('can_manage'),
            ]
        );

        return redirect()
            ->route('media.albums.permissions.edit', $album)
            ->with('success', 'Override utilisateur ajouté.');
    }

    /**
     * Supprime un override utilisateur.
     */
    public function destroyUser(MediaAlbum $album, MediaAlbumUserPermission $perm): RedirectResponse
    {
        $this->authorize('manage', $album);

        $perm->delete();

        return redirect()
            ->route('media.albums.permissions.edit', $album)
            ->with('success', 'Override supprimé.');
    }
}
