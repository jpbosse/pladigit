<?php

namespace App\Http\Controllers\Ged;

use App\Enums\GedPermissionLevel;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Department;
use App\Models\Tenant\GedFolder;
use App\Models\Tenant\GedFolderPermission;
use App\Models\Tenant\GedFolderUserPermission;
use App\Models\Tenant\User;
use App\Services\AuditService;
use App\Services\Ged\GedPermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GedPermissionController extends Controller
{
    public function __construct(
        private readonly GedPermissionService $permissions,
        private readonly AuditService $audit,
    ) {}

    /**
     * Affiche l'interface de gestion des droits d'un dossier.
     */
    public function index(GedFolder $folder): View
    {
        $this->authorize('managePermissions', $folder);

        $perms = $this->permissions->permissionsFor($folder);
        $roles = UserRole::cases();
        $departments = Department::orderBy('type')->orderBy('name')->get();
        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        $sidebarTree = collect();

        return view('ged.permissions.index', compact(
            'folder', 'perms', 'roles', 'departments', 'users', 'sidebarTree'
        ));
    }

    /**
     * Définit ou met à jour la permission d'un rôle sur le dossier.
     */
    public function setRole(Request $request, GedFolder $folder): RedirectResponse|JsonResponse
    {
        $this->authorize('managePermissions', $folder);

        $validated = $request->validate([
            'role' => ['required', 'string', 'in:'.implode(',', array_column(UserRole::cases(), 'value'))],
            'level' => ['required', 'string', 'in:'.implode(',', array_column(GedPermissionLevel::cases(), 'value'))],
        ]);

        $this->permissions->setRolePermission(
            $folder,
            $validated['role'],
            GedPermissionLevel::from($validated['level'])
        );

        /** @var User $user */
        $user = auth()->user();
        $this->audit->log('ged.permission.role.set', $user, [
            'model_type' => GedFolder::class,
            'model_id' => $folder->id,
            'new' => ['role' => $validated['role'], 'level' => $validated['level']],
        ]);

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Droit rôle mis à jour.');
    }

    /**
     * Définit ou met à jour la permission d'un département sur le dossier.
     */
    public function setDepartment(Request $request, GedFolder $folder): RedirectResponse|JsonResponse
    {
        $this->authorize('managePermissions', $folder);

        $validated = $request->validate([
            'department_id' => ['required', 'integer', 'exists:tenant.departments,id'],
            'level' => ['required', 'string', 'in:'.implode(',', array_column(GedPermissionLevel::cases(), 'value'))],
        ]);

        $department = Department::findOrFail((int) $validated['department_id']);

        $this->permissions->setDepartmentPermission(
            $folder,
            $department,
            GedPermissionLevel::from($validated['level'])
        );

        /** @var User $user */
        $user = auth()->user();
        $this->audit->log('ged.permission.department.set', $user, [
            'model_type' => GedFolder::class,
            'model_id' => $folder->id,
            'new' => ['department_id' => $department->id, 'level' => $validated['level']],
        ]);

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Droit département mis à jour.');
    }

    /**
     * Définit ou met à jour la permission d'un utilisateur individuel sur le dossier.
     */
    public function setUser(Request $request, GedFolder $folder): RedirectResponse|JsonResponse
    {
        $this->authorize('managePermissions', $folder);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:tenant.users,id'],
            'level' => ['required', 'string', 'in:'.implode(',', array_column(GedPermissionLevel::cases(), 'value'))],
        ]);

        $target = User::findOrFail((int) $validated['user_id']);

        $this->permissions->setUserPermission(
            $folder,
            $target,
            GedPermissionLevel::from($validated['level'])
        );

        /** @var User $user */
        $user = auth()->user();
        $this->audit->log('ged.permission.user.set', $user, [
            'model_type' => GedFolder::class,
            'model_id' => $folder->id,
            'new' => ['user_id' => $target->id, 'level' => $validated['level']],
        ]);

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Droit utilisateur mis à jour.');
    }

    /**
     * Supprime une permission par sujet (rôle / direction / service).
     */
    public function destroySubject(Request $request, GedFolder $folder): RedirectResponse|JsonResponse
    {
        $this->authorize('managePermissions', $folder);

        $validated = $request->validate([
            'permission_id' => ['required', 'integer', 'exists:tenant.ged_folder_permissions,id'],
        ]);

        GedFolderPermission::where('id', (int) $validated['permission_id'])
            ->where('folder_id', $folder->id)
            ->delete();

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Permission supprimée.');
    }

    /**
     * Supprime la permission individuelle d'un utilisateur sur le dossier.
     */
    public function destroyUser(Request $request, GedFolder $folder): RedirectResponse|JsonResponse
    {
        $this->authorize('managePermissions', $folder);

        $validated = $request->validate([
            'permission_id' => ['required', 'integer', 'exists:tenant.ged_folder_user_permissions,id'],
        ]);

        GedFolderUserPermission::where('id', (int) $validated['permission_id'])
            ->where('folder_id', $folder->id)
            ->delete();

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Permission utilisateur supprimée.');
    }
}
