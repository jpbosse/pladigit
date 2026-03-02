<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Department;
use App\Models\Tenant\User;
use App\Services\AuditService;
use App\Services\PasswordPolicyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Gestion des utilisateurs par l'Admin Organisation.
 *
 * Visibilité :
 *   - admin / president / dgs → tous les utilisateurs
 *   - resp_direction → membres de ses directions et services enfants
 *   - resp_service   → membres de ses services
 *   - user           → lui-même uniquement (redirigé vers profil)
 */
class UserController extends Controller
{
    public function __construct(
        private PasswordPolicyService $policy,
        private AuditService $audit,
    ) {}

    public function index()
    {
        $currentUser = auth()->user();
        $role        = UserRole::tryFrom($currentUser->role ?? '');

        // Périmètre visible selon le rôle
        if ($role && $role->atLeast(UserRole::DGS)) {
            $users = User::on('tenant')->orderBy('name')->paginate(25);
        } elseif ($role === UserRole::RESP_DIRECTION) {
            $visibleIds = $currentUser->visibleUsers()->pluck('id');
            $users      = User::on('tenant')->whereIn('id', $visibleIds)->orderBy('name')->paginate(25);
        } elseif ($role === UserRole::RESP_SERVICE) {
            $visibleIds = $currentUser->visibleUsers()->pluck('id');
            $users      = User::on('tenant')->whereIn('id', $visibleIds)->orderBy('name')->paginate(25);
        } else {
            return redirect()->route('profile.show');
        }

        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        $directions = Department::on('tenant')->directions()->orderBy('name')->get();
        $services   = Department::on('tenant')->services()->with('parent')->orderBy('name')->get();

        return view('admin.users.create', compact('directions', 'services'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'email'          => ['required', 'email', 'unique:tenant.users'],
            'role'           => ['required', UserRole::rule()],
            'department_ids' => ['nullable', 'array'],
            'department_ids.*' => ['exists:tenant.departments,id'],
            'new_department_name' => ['nullable', 'string', 'max:255'],
            'new_department_type' => ['nullable', 'in:direction,service'],
            'new_department_parent_id' => ['nullable', 'exists:tenant.departments,id'],
        ]);

        // Appliquer la politique de mot de passe du tenant (§17.1)
        $policyErrors = $this->policy->validate($request->password ?? '');
        if (! empty($policyErrors)) {
            return back()->withErrors(['password' => $policyErrors])->withInput();
        }

        $user = User::on('tenant')->create([
            'name'            => $request->name,
            'email'           => $request->email,
            'role'            => $request->role,
            'password_hash'   => Hash::make($request->password ?? ''),
            'status'          => 'active',
            'force_pwd_change' => true,
            'created_by'      => auth()->id(),
        ]);

        // Créer un nouveau département si demandé
        if ($request->filled('new_department_name') && $request->filled('new_department_type')) {
            $newDept = Department::on('tenant')->create([
                'name'       => $request->new_department_name,
                'type'       => $request->new_department_type,
                'parent_id'  => $request->new_department_parent_id,
                'created_by' => auth()->id(),
            ]);

            $request->merge([
                'department_ids' => array_merge($request->department_ids ?? [], [$newDept->id]),
            ]);
        }

        // Affecter aux départements
        if ($request->department_ids) {
            $userRole  = UserRole::tryFrom($request->role);
            $isManager = $userRole && in_array($userRole, [UserRole::RESP_DIRECTION, UserRole::RESP_SERVICE]);

            $sync = [];
            foreach ($request->department_ids as $deptId) {
                $sync[$deptId] = ['is_manager' => $isManager];
            }
            $user->departments()->sync($sync);
        }

        $this->audit->log('user.created', auth()->user(), [
            'model_type' => User::class,
            'model_id'   => $user->id,
            'new'        => ['email' => $user->email, 'role' => $user->role],
        ]);

        return redirect()->route('admin.users.index')
            ->with('success', "Utilisateur {$user->email} créé.");
    }

    public function edit(User $user)
    {
        $directions = Department::on('tenant')->directions()->orderBy('name')->get();
        $services   = Department::on('tenant')->services()->with('parent')->orderBy('name')->get();
        $userDeptIds = $user->departments()->pluck('departments.id')->toArray();

        return view('admin.users.edit', compact('user', 'directions', 'services', 'userDeptIds'));
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'role'           => ['required', UserRole::rule()],
            'status'         => ['required', 'in:active,inactive,locked'],
            'department_ids' => ['nullable', 'array'],
            'department_ids.*' => ['exists:tenant.departments,id'],
            'new_department_name' => ['nullable', 'string', 'max:255'],
            'new_department_type' => ['nullable', 'in:direction,service'],
            'new_department_parent_id' => ['nullable', 'exists:tenant.departments,id'],
        ]);

        $old = $user->only(['name', 'role', 'status']);

        $user->update([
            'name'   => $request->name,
            'role'   => $request->role,
            'status' => $request->status,
        ]);

        // Changement de mot de passe optionnel
        if ($request->filled('password')) {
            $request->validate(['password' => ['string', 'confirmed']]);
            $policyErrors = $this->policy->validate($request->password);
            if (! empty($policyErrors)) {
                return back()->withErrors(['password' => $policyErrors])->withInput();
            }
            $this->policy->updatePassword($user, $request->password);
        }

        // Créer un nouveau département si demandé
        if ($request->filled('new_department_name') && $request->filled('new_department_type')) {
            $newDept = Department::on('tenant')->create([
                'name'       => $request->new_department_name,
                'type'       => $request->new_department_type,
                'parent_id'  => $request->new_department_parent_id,
                'created_by' => auth()->id(),
            ]);

            $request->merge([
                'department_ids' => array_merge($request->department_ids ?? [], [$newDept->id]),
            ]);
        }

        // Resynchroniser les départements
        $userRole  = UserRole::tryFrom($request->role);
        $isManager = $userRole && in_array($userRole, [UserRole::RESP_DIRECTION, UserRole::RESP_SERVICE]);

        $sync = [];
        foreach ($request->department_ids ?? [] as $deptId) {
            $sync[$deptId] = ['is_manager' => $isManager];
        }
        $user->departments()->sync($sync);

        $this->audit->log('user.updated', auth()->user(), [
            'model_type' => User::class,
            'model_id'   => $user->id,
            'old'        => $old,
            'new'        => $user->only(['name', 'role', 'status']),
        ]);

        return redirect()->route('admin.users.index')
            ->with('success', "Utilisateur {$user->email} mis à jour.");
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->withErrors(['error' => 'Vous ne pouvez pas supprimer votre propre compte.']);
        }

        $user->update(['status' => 'inactive']);

        $this->audit->log('user.deactivated', auth()->user(), [
            'model_type' => User::class,
            'model_id'   => $user->id,
        ]);

        return back()->with('success', "Utilisateur {$user->email} désactivé.");
    }

    public function resetPassword(User $user)
    {
        $password = \Illuminate\Support\Str::random(12);
        $user->update([
            'password_hash'   => Hash::make($password),
            'force_pwd_change' => true,
        ]);

        $this->audit->log('user.password_reset', auth()->user(), [
            'model_type' => User::class,
            'model_id'   => $user->id,
        ]);

        // ⚠ TODO §17.4 — Remplacer par un e-mail d'invitation (Phase 2)
        return back()->with('success', "Nouveau mot de passe : {$password} (à communiquer à l'utilisateur)");
    }
}
