<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Department;
use App\Models\Tenant\User;
use App\Services\AuditService;
use App\Services\InvitationService;
use App\Services\PasswordPolicyService;
use Illuminate\Http\Request;

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
        private InvitationService $invitation,
    ) {}

    public function index()
    {
        $currentUser = auth()->user();
        $role = UserRole::tryFrom($currentUser->role ?? '');

        if ($role && $role->atLeast(UserRole::DGS)) {
            $users = User::on('tenant')->orderBy('name')->paginate(25);
        } elseif ($role === UserRole::RESP_DIRECTION) {
            $visibleIds = $currentUser->visibleUsers()->pluck('id');
            $users = User::on('tenant')->whereIn('id', $visibleIds)->orderBy('name')->paginate(25);
        } elseif ($role === UserRole::RESP_SERVICE) {
            $visibleIds = $currentUser->visibleUsers()->pluck('id');
            $users = User::on('tenant')->whereIn('id', $visibleIds)->orderBy('name')->paginate(25);
        } else {
            return redirect()->route('profile.show');
        }

        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        $deptTree = Department::on('tenant')
            ->whereNull('parent_id')
            ->with('allChildren')
            ->orderBy('label')->orderBy('name')
            ->get();

        return view('admin.users.create', compact('deptTree'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:tenant.users'],
            'role' => ['required', UserRole::rule()],
            'department_ids' => ['nullable', 'array'],
            'new_department_name' => ['nullable', 'string', 'max:255'],
            'new_department_type' => ['nullable', 'in:direction,service'],
        ]);

        // Valider les départements manuellement sur la connexion tenant
        foreach ($request->department_ids ?? [] as $deptId) {
            if (! Department::on('tenant')->where('id', $deptId)->exists()) {
                return back()->withErrors(['department_ids' => 'Un département sélectionné est invalide.'])->withInput();
            }
        }

        if ($request->filled('new_department_parent_id')) {
            if (! Department::on('tenant')->where('id', $request->new_department_parent_id)->exists()) {
                return back()->withErrors(['new_department_parent_id' => 'La direction parente est invalide.'])->withInput();
            }
        }

        // Création sans mot de passe — sera défini lors de l'activation via email
        $user = User::on('tenant')->create([
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
            'password_hash' => null,
            'status' => 'inactive', // Actif après activation invitation
            'force_pwd_change' => false,
            'created_by' => auth()->id(),
        ]);

        if ($request->filled('new_department_name') && $request->filled('new_department_type')) {
            $newDept = Department::on('tenant')->create([
                'name' => $request->new_department_name,
                'type' => $request->new_department_type,
                'parent_id' => $request->new_department_parent_id,
                'created_by' => auth()->id(),
            ]);

            $request->merge([
                'department_ids' => array_merge($request->department_ids ?? [], [$newDept->id]),
            ]);
        }

        if ($request->department_ids) {
            $userRole = UserRole::tryFrom($request->role);
            $isManager = $userRole && in_array($userRole, [UserRole::RESP_DIRECTION, UserRole::RESP_SERVICE]);

            $sync = [];
            foreach ($request->department_ids as $deptId) {
                $sync[$deptId] = ['is_manager' => $isManager];
            }
            $user->departments()->sync($sync);
        }

        // Génération du token et envoi de l'email d'invitation
        $token = $this->invitation->generate($user);
        $this->invitation->sendInvitation($user, $token, auth()->user()->name);

        $this->audit->log('user.created', auth()->user(), [
            'model_type' => User::class,
            'model_id' => $user->id,
            'new' => ['email' => $user->email, 'role' => $user->role],
        ]);

        return redirect()->route('admin.users.index')
            ->with('success', "Invitation envoyée à {$user->email}. Le compte sera actif après activation.");
    }

    public function edit(User $user)
    {
        $deptTree = Department::on('tenant')
            ->whereNull('parent_id')
            ->with('allChildren')
            ->orderBy('label')->orderBy('name')
            ->get();
        $userDeptIds = $user->departments()->pluck('departments.id')->toArray();

        return view('admin.users.edit', compact('user', 'deptTree', 'userDeptIds'));
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', UserRole::rule()],
            'status' => ['required', 'in:active,inactive,locked'],
            'department_ids' => ['nullable', 'array'],
            'new_department_name' => ['nullable', 'string', 'max:255'],
            'new_department_type' => ['nullable', 'in:direction,service'],
        ]);

        // Valider les départements manuellement sur la connexion tenant
        foreach ($request->department_ids ?? [] as $deptId) {
            if (! Department::on('tenant')->where('id', $deptId)->exists()) {
                return back()->withErrors(['department_ids' => 'Un département sélectionné est invalide.'])->withInput();
            }
        }

        if ($request->filled('new_department_parent_id')) {
            if (! Department::on('tenant')->where('id', $request->new_department_parent_id)->exists()) {
                return back()->withErrors(['new_department_parent_id' => 'La direction parente est invalide.'])->withInput();
            }
        }

        $old = $user->only(['name', 'role', 'status']);

        $user->update([
            'name' => $request->name,
            'role' => $request->role,
            'status' => $request->status,
        ]);

        if ($request->filled('password')) {
            $request->validate(['password' => ['string', 'confirmed']]);
            $policyErrors = $this->policy->validate($request->password);
            if (! empty($policyErrors)) {
                return back()->withErrors(['password' => $policyErrors])->withInput();
            }
            $this->policy->updatePassword($user, $request->password);
        }

        if ($request->filled('new_department_name') && $request->filled('new_department_type')) {
            $newDept = Department::on('tenant')->create([
                'name' => $request->new_department_name,
                'type' => $request->new_department_type,
                'parent_id' => $request->new_department_parent_id,
                'created_by' => auth()->id(),
            ]);

            $request->merge([
                'department_ids' => array_merge($request->department_ids ?? [], [$newDept->id]),
            ]);
        }

        $userRole = UserRole::tryFrom($request->role);
        $isManager = $userRole && in_array($userRole, [UserRole::RESP_DIRECTION, UserRole::RESP_SERVICE]);

        $sync = [];
        foreach ($request->department_ids ?? [] as $deptId) {
            $sync[$deptId] = ['is_manager' => $isManager];
        }
        $user->departments()->sync($sync);

        $this->audit->log('user.updated', auth()->user(), [
            'model_type' => User::class,
            'model_id' => $user->id,
            'old' => $old,
            'new' => $user->only(['name', 'role', 'status']),
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
            'model_id' => $user->id,
        ]);

        return back()->with('success', "Utilisateur {$user->email} désactivé.");
    }

    public function resetPassword(User $user)
    {
        // Générer un nouveau token d'invitation et envoyer l'email
        // Supprime le mot de passe existant jusqu'à la réactivation
        $token = $this->invitation->generate($user);
        $this->invitation->sendInvitation($user, $token, auth()->user()->name);

        $this->audit->log('user.password_reset', auth()->user(), [
            'model_type' => User::class,
            'model_id' => $user->id,
        ]);

        return back()->with('success', "Un email de réinitialisation a été envoyé à {$user->email}.");
    }
}
