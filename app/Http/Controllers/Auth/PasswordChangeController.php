<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\PasswordPolicyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Gère le changement de mot de passe forcé et volontaire.
 */
class PasswordChangeController extends Controller
{
    public function __construct(private PasswordPolicyService $policy) {}

    public function showForced()
    {
        return view('auth.password-change-forced');
    }

    public function updateForced(Request $request)
    {
        $request->validate([
            'password' => ['required', 'string', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ]);

        $user = Auth::user();
        $errors = $this->policy->validate($request->password, $user->password_history ?? []);

        if (! empty($errors)) {
            return back()->withErrors(['password' => $errors]);
        }

        $this->policy->updatePassword($user, $request->password);

        return redirect()->route('dashboard')->with('success', 'Mot de passe mis à jour avec succès.');
    }
}
