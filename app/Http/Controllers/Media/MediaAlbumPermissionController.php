<?php

namespace App\Http\Controllers\Media;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Department;
use App\Models\Tenant\MediaAlbum;
use App\Models\Tenant\Share;
use App\Models\Tenant\User;
use App\Services\ShareService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MediaAlbumPermissionController extends Controller
{
    public function __construct(private ShareService $shareService) {}

    /**
     * Page de gestion des droits d'un album.
     */
    public function edit(MediaAlbum $album): View
    {
        $this->authorize('manage', $album);

        $shares = $this->shareService->sharesFor($album);

        $roleShares = $shares->where('shared_with_type', 'role')->keyBy('shared_with_role');
        $deptShares = $shares->where('shared_with_type', 'department');
        $userShares = $shares->where('shared_with_type', 'user');

        $configurableRoles = [
            UserRole::RESP_DIRECTION->value => UserRole::RESP_DIRECTION->label(),
            UserRole::RESP_SERVICE->value   => UserRole::RESP_SERVICE->label(),
            UserRole::USER->value           => UserRole::USER->label(),
        ];

        $departments = Department::orderBy('name')->get();

        $users = User::whereNotIn('role', [
            UserRole::ADMIN->value,
            UserRole::PRESIDENT->value,
            UserRole::DGS->value,
        ])->orderBy('name')->get();

        return view('media.albums.permissions', compact(
            'album',
            'roleShares',
            'deptShares',
            'userShares',
            'configurableRoles',
            'departments',
            'users',
        ));
    }

    /**
     * Enregistre les droits par rôle.
     */
    public function updateRoles(Request $request, MediaAlbum $album): RedirectResponse
    {
        $this->authorize('manage', $album);

        $allConfigurable = [
            UserRole::RESP_DIRECTION->value,
            UserRole::RESP_SERVICE->value,
            UserRole::USER->value,
        ];

        $data = $request->input('roles', []);

        foreach ($allConfigurable as $role) {
            $perms = $data[$role] ?? [];
            $this->shareService->upsert(
                object: $album,
                withType: 'role',
                withId: null,
                withRole: $role,
                abilities: [
                    'can_view'     => isset($perms['can_view']),
                    'can_download' => isset($perms['can_download']),
                    'can_edit'     => isset($perms['can_edit']),
                    'can_manage'   => isset($perms['can_manage']),
                ],
                sharedBy: auth()->id(),
            );
        }

        return redirect()
            ->route('media.albums.permissions.edit', $album)
            ->with('success', 'Droits par rôle enregistrés.');
    }

    /**
     * Ajoute un partage (utilisateur ou département).
     */
    public function store(Request $request, MediaAlbum $album): RedirectResponse
    {
        $this->authorize('manage', $album);

        $request->validate([
            'shared_with_type' => ['required', 'in:user,department'],
            'shared_with_id'   => ['required', 'integer'],
        ]);

        $this->shareService->upsert(
            object: $album,
            withType: $request->input('shared_with_type'),
            withId: $request->integer('shared_with_id'),
            withRole: null,
            abilities: [
                'can_view'     => $request->boolean('can_view'),
                'can_download' => $request->boolean('can_download'),
                'can_edit'     => $request->boolean('can_edit'),
                'can_manage'   => $request->boolean('can_manage'),
            ],
            sharedBy: auth()->id(),
        );

        return redirect()
            ->route('media.albums.permissions.edit', $album)
            ->with('success', 'Partage ajouté.');
    }

    /**
     * Supprime un partage.
     */
    public function destroy(MediaAlbum $album, Share $share): RedirectResponse
    {
        $this->authorize('manage', $album);

        $this->shareService->revoke($share);

        return redirect()
            ->route('media.albums.permissions.edit', $album)
            ->with('success', 'Partage supprimé.');
    }
}
