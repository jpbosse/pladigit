<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
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
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'unique:tenant.users'],
            'role'     => ['required', 'in:admin,president,dgs,resp_direction,resp_service,user'],
            'password' => ['required', 'min:8', 'confirmed'],
            'department' => ['nullable', 'string', 'max:255'],
        ]);

        User::create([
            'name'          => $validated['name'],
            'email'         => $validated['email'],
            'role'          => $validated['role'],
            'department'    => $validated['department'] ?? null,
            'password_hash' => Hash::make($validated['password']),
            'status'        => 'active',
        ]);

        return redirect()
            ->route('admin.users.index')
            ->with('success', "Utilisateur {$validated['email']} créé.");
    }

    public function edit(User $user)
    {
        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'role'       => ['required', 'in:admin,president,dgs,resp_direction,resp_service,user'],
            'status'     => ['required', 'in:active,inactive,locked'],
            'department' => ['nullable', 'string', 'max:255'],
        ]);

        $user->update($validated);

        if ($request->filled('password')) {
            $request->validate(['password' => ['min:8', 'confirmed']]);
            $user->update(['password_hash' => Hash::make($request->password)]);
        }

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

        return back()->with('success', "Utilisateur {$user->email} désactivé.");
    }

    public function resetPassword(User $user)
    {
        $password = \Illuminate\Support\Str::random(12);
        $user->update([
            'password_hash'      => Hash::make($password),
            'force_pwd_change'   => true,
        ]);

        return back()->with('success', "Nouveau mot de passe : {$password} (à communiquer à l'utilisateur)");
    }
}
