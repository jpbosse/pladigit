<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PragmaRX\Google2FA\Google2FA;

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

        // Si aucun secret TOTP configuré → accès direct (mode legacy)
        if (! config('superadmin.totp_secret')) {
            session([
                'super_admin_email' => $request->email,
                'super_admin_verified' => true,
            ]);

            return redirect()->route('super-admin.dashboard');
        }

        // Email+password OK → étape TOTP
        session(['super_admin_totp_pending' => $request->email]);

        return redirect()->route('super-admin.login.totp');
    }

    public function showTotpForm()
    {
        if (! session('super_admin_totp_pending')) {
            return redirect()->route('super-admin.login');
        }

        return view('super-admin.login-totp');
    }

    public function verifyTotp(Request $request)
    {
        $email = session('super_admin_totp_pending');

        if (! $email || $email !== config('superadmin.email')) {
            return redirect()->route('super-admin.login');
        }

        $request->validate(['code' => ['required', 'digits:6']]);

        $g2fa = new Google2FA;
        $secret = config('superadmin.totp_secret');
        $valid = $g2fa->verifyKey($secret, $request->code);

        if (! $valid) {
            return back()->withErrors(['code' => 'Code incorrect ou expiré.']);
        }

        session()->forget('super_admin_totp_pending');
        session([
            'super_admin_email' => $email,
            'super_admin_verified' => true,
        ]);

        return redirect()->route('super-admin.dashboard');
    }

    public function logout(Request $request)
    {
        $request->session()->forget([
            'super_admin_email',
            'super_admin_verified',
            'super_admin_totp_pending',
        ]);

        return redirect()->route('super-admin.login');
    }
}
