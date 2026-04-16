<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use PragmaRX\Google2FA\Google2FA;

class SecurityController extends Controller
{
    public function totpSetup()
    {
        $g2fa = new Google2FA;
        $secret = $g2fa->generateSecretKey(32);

        $uri = $g2fa->getQRCodeUrl('Pladigit SA', config('superadmin.email'), $secret);

        $writer = new Writer(new ImageRenderer(new RendererStyle(220), new SvgImageBackEnd));
        $qrCode = $writer->writeString($uri);

        session(['sa_totp_setup_secret' => $secret]);

        return view('super-admin.security-totp', [
            'qr_code' => $qrCode,
            'secret' => $secret,
            'already_enabled' => (bool) config('superadmin.totp_secret'),
        ]);
    }

    public function totpConfirm(Request $request)
    {
        $request->validate(['code' => ['required', 'digits:6']]);

        $secret = session('sa_totp_setup_secret');

        if (! $secret) {
            return redirect()->route('super-admin.security.totp')
                ->withErrors(['code' => 'Session expirée, recommencez.']);
        }

        $valid = (new Google2FA)->verifyKey($secret, $request->code);

        if (! $valid) {
            return back()->withErrors(['code' => 'Code incorrect ou expiré.']);
        }

        session()->forget('sa_totp_setup_secret');

        return view('super-admin.security-totp-confirmed', ['secret' => $secret]);
    }
}
