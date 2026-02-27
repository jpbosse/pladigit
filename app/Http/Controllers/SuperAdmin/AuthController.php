<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        return view('super-admin.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (
            $request->email !== config('superadmin.email') ||
            ! \Illuminate\Support\Facades\Hash::check($request->password, config('superadmin.password_hash'))
        ) {
            return back()->withErrors(['email' => 'Identifiants incorrects.']);
        }

        session([
            'super_admin_email' => $request->email,
            'super_admin_verified' => true,
        ]);

        return redirect()->route('super-admin.dashboard');
    }

    public function logout(Request $request)
    {
        $request->session()->forget(['super_admin_email', 'super_admin_verified']);

        return redirect()->route('super-admin.login');
    }
}
