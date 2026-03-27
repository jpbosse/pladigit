<?php

namespace App\Http\Controllers\Media;

use App\Enums\AlbumPermissionLevel;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Tenant\AlbumPermission;
use App\Models\Tenant\AlbumUserPermission;
use App\Models\Tenant\Department;
use App\Models\Tenant\MediaAlbum;
use App\Models\Tenant\User;
use App\Services\AlbumPermissionService;
use Illuminate\Http\Request;

/**
 * Gestion des permissions d'un album.
 * Accessible uniquement aux utilisateurs pouvant administrer l'album.
 */
class AlbumPermissionController extends Controller
{
    public function __construct(
        private readonly AlbumPermissionService $permissionService,
    ) {}

    /**
     * Page de gestion des permissions d'un album.
     */
    public function edit(MediaAlbum $album)
    {
        $this->authorize('manage', $album);

        $permissions = $this->permissionService->permissionsFor($album);

        // Arborescence complète récursive (N niveaux)
        $deptTree = Department::whereNull('parent_id')
            ->with('allChildren')
            ->orderBy('label')->orderBy('name')
            ->get();

        // Garder aussi les listes plates pour la résolution des permissions existantes
        $directions = Department::where('type', 'direction')->orderBy('name')->get(['id', 'name', 'label']);
        $services = Department::where('type', 'service')->with('parentDept')->orderBy('name')->get(['id', 'name', 'parent_id', 'label']);
        $users = User::where('status', 'active')->orderBy('name')->get(['id', 'name', 'role']);
        $levels = AlbumPermissionLevel::options();

        // Héritage parent — album direct + ses permissions pour affichage dans la vue
        $inheritedFrom = $album->parent_id
            ? MediaAlbum::find($album->parent_id)
            : null;

        $parentPermissions = $inheritedFrom
            ? $this->permissionService->permissionsFor($inheritedFrom)
            : null;

        // Arborescence aplatie pour Alpine.js (filtre recherche)
        $deptTreeFlat = $this->flattenDeptTree($deptTree);

        // Détection des redondances : permissions couvertes par un ancêtre
        $redundancies = $this->detectRedundancies($permissions, $deptTree);

        // Rôles configurables (Admin/Président/DGS ont accès total garanti)
        $roles = [UserRole::RESP_DIRECTION, UserRole::RESP_SERVICE, UserRole::USER];

        return view('media.albums.permissions', compact(
            'album',
            'permissions',
            'roles',
            'deptTree',
            'deptTreeFlat',
            'directions',
            'services',
            'users',
            'levels',
            'inheritedFrom',
            'parentPermissions',
            'redundancies',
        ));
    }

    /**
     * Ajoute ou met à jour une permission rôle/direction/service.
     */
    public function storeSubject(Request $request, MediaAlbum $album)
    {
        $this->authorize('manage', $album);

        $validated = $request->validate([
            'subject_type' => ['required', 'in:role,direction,service'],
            'subject_id' => ['nullable', 'integer'],
            'subject_role' => ['nullable', 'in:resp_direction,resp_service,user'],
            'level' => ['required', 'in:none,view,download,admin'],
        ]);

        // Selon le type, valider subject_id ou subject_role
        if ($validated['subject_type'] === 'role') {
            $request->validate(['subject_role' => ['required', 'string']]);
            $validated['subject_id'] = null;
        } else {
            $request->validate(['subject_id' => ['required', 'integer',
                'exists:tenant.departments,id']]);
            $validated['subject_role'] = null;
        }

        AlbumPermission::updateOrCreate(
            [
                'album_id' => $album->id,
                'subject_type' => $validated['subject_type'],
                'subject_id' => $validated['subject_id'],
                'subject_role' => $validated['subject_role'],
            ],
            ['level' => $validated['level']]
        );

        return redirect()->route('media.albums.permissions.edit', $album)->with('success', 'Permission mise à jour.');
    }

    /**
     * Ajoute ou met à jour une permission utilisateur individuel.
     */
    public function storeUser(Request $request, MediaAlbum $album)
    {
        $this->authorize('manage', $album);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:tenant.users,id'],
            'level' => ['required', 'in:none,view,download,admin'],
        ]);

        AlbumUserPermission::updateOrCreate(
            ['album_id' => $album->id, 'user_id' => $validated['user_id']],
            ['level' => $validated['level']]
        );

        return redirect()->route('media.albums.permissions.edit', $album)->with('success', 'Permission utilisateur mise à jour.');
    }

    /**
     * Supprime une permission sujet (rôle/direction/service).
     */
    public function destroySubject(MediaAlbum $album, AlbumPermission $permission)
    {
        $this->authorize('manage', $album);
        $permission->delete();

        return redirect()->route('media.albums.permissions.edit', $album)->with('success', 'Permission supprimée.');
    }

    /**
     * Supprime une permission utilisateur.
     */
    public function destroyUser(MediaAlbum $album, AlbumUserPermission $permission)
    {
        $this->authorize('manage', $album);
        $permission->delete();

        return redirect()->route('media.albums.permissions.edit', $album)->with('success', 'Permission utilisateur supprimée.');
    }

    // ── Helpers privés ───────────────────────────────────────────────────────

    /**
     * Aplatit l'arborescence des départements pour Alpine.js.
     * Retourne un tableau JSON-sérialisable.
     *
     * @param  \Illuminate\Support\Collection  $nodes
     * @return array<int, array{id: int, name: string, label: string, typeLabel: string, type: string, icon: string, depth: int, parent: string|null}>
     */
    private function flattenDeptTree($nodes, int $depth = 0, ?string $parentName = null): array
    {
        $result = [];
        foreach ($nodes as $node) {
            $typeLabel = $node->label ?? ($node->parent_id ? 'Service' : 'Direction');
            $icon = $node->parent_id ? '📂' : '🏢';
            $result[] = [
                'id' => $node->id,
                'name' => $node->name,
                'label' => ($node->label ? $node->label.' ' : '').$node->name,
                'typeLabel' => $typeLabel,
                'type' => $node->parent_id ? 'service' : 'direction',
                'icon' => $icon,
                'depth' => $depth,
                'parent' => $parentName,
            ];
            if ($node->allChildren && $node->allChildren->isNotEmpty()) {
                $result = array_merge(
                    $result,
                    $this->flattenDeptTree($node->allChildren, $depth + 1, $node->name)
                );
            }
        }

        return $result;
    }

    /**
     * Détecte les permissions redondantes :
     * une permission sur un nœud enfant avec le même niveau qu'un ancêtre.
     * Retourne un Set des subject_id redondants.
     *
     * @return array<int>
     */
    private function detectRedundancies(array $permissions, $deptTree): array
    {
        $deptPerms = collect($permissions['department'])->keyBy('subject_id');
        $redundant = [];

        foreach ($deptPerms as $subjId => $perm) {
            // Remonter les ancêtres de ce département
            $dept = \App\Models\Tenant\Department::find($subjId);
            if (! $dept) {
                continue;
            }

            $ancestors = $dept->ancestors(); // array de Department
            foreach ($ancestors as $ancestor) {
                if ($deptPerms->has($ancestor->id)) {
                    $ancestorPerm = $deptPerms->get($ancestor->id);
                    // Redondant si même niveau ou ancêtre plus permissif
                    if ($ancestorPerm->level->value === $perm->level->value) {
                        $redundant[] = $subjId;
                        break;
                    }
                }
            }
        }

        return $redundant;
    }
}
