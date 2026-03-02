<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Tenant\User;
use App\Services\AuditService;
use App\Services\PasswordPolicyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Gestion des utilisateurs par l'Admin Organisation.
 *
 * PasswordPolicyService est injecté pour appliquer la politique
 * de mots de passe du tenant sur chaque création/modification.
 * (Correction §17.1 — CDC v1.2)
 */
class UserController extends Controller
{
    public function __construct(
        private PasswordPolicyService $policy,
        private AuditService $audit,
    ) {}

    public function index()
    {
        $users = User::orderBy('name')->paginate(25);

        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        return view('admin.users.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'email'      => ['required', 'email', 'unique:tenant.users'],
            'role'       => ['required', UserRole::rule()],
            'department' => ['nullable', 'string', 'max:255'],
        ]);

        // Appliquer la politique de mot de passe du tenant (§17.1)
        $policyErrors = $this->policy->validate($request->password);
        if (! empty($policyErrors)) {
            return back()
                ->withErrors(['password' => $policyErrors])
                ->withInput();
        }

        $user = User::create([
            'name'             => $request->name,
            'email'            => $request->email,
            'role'             => $request->role,
            'department'       => $request->department,
            'password_hash'    => Hash::make($request->password),
            'status'           => 'active',
            'force_pwd_change' => true, // L'utilisateur devra changer son mot de passe à la 1ère connexion
        ]);

        $this->audit->log('user.created', auth()->user(), [
            'model_type' => User::class,
            'model_id'   => $user->id,
            'new'        => ['email' => $user->email, 'role' => $user->role],
        ]);

        return redirect()
            ->route('admin.users.index')
            ->with('success', "Utilisateur {$user->email} créé.");
    }

    public function edit(User $user)
    {
        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'role'       => ['required', UserRole::rule()],
            'department' => ['nullable', 'string', 'max:255'],
        ]);

        $old = $user->only(['name', 'role', 'status', 'department']);

        $user->update([
            'name'       => $request->name,
            'role'       => $request->role,
            'status'     => $request->status,
            'department' => $request->department,
        ]);

        // Changement de mot de passe optionnel — validé par la politique tenant
        if ($request->filled('password')) {
            $request->validate(['password' => ['string', 'confirmed']]);

            $policyErrors = $this->policy->validate($request->password);
            if (! empty($policyErrors)) {
                return back()
                    ->withErrors(['password' => $policyErrors])
                    ->withInput();
            }

            $this->policy->updatePassword($user, $request->password);
        }

        $this->audit->log('user.updated', auth()->user(), [
            'model_type' => User::class,
            'model_id'   => $user->id,
            'old'        => $old,
            'new'        => $user->only(['name', 'role', 'status', 'department']),
        ]);

        return redirect()
            ->route('admin.users.index')
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
            'password_hash'    => Hash::make($password),
            'force_pwd_change' => true,
        ]);

        $this->audit->log('user.password_reset', auth()->user(), [
            'model_type' => User::class,
            'model_id'   => $user->id,
        ]);

        // ⚠ TODO §17.4 — Remplacer par un e-mail d'invitation (Phase 2)
        // Le mot de passe temporaire ne devrait pas être affiché en clair.
        return back()->with('success', "Nouveau mot de passe : {$password} (à communiquer à l'utilisateur)");
    }
}
