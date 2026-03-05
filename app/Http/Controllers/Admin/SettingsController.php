<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\TenantSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class SettingsController extends Controller
{
    public function ldap()
    {
        $settings = TenantSettings::firstOrCreate([]);

        return view('admin.settings.ldap', compact('settings'));
    }

    public function updateLdap(Request $request)
    {
        $validated = $request->validate([
            'ldap_host' => ['nullable', 'string', 'max:255'],
            'ldap_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'ldap_base_dn' => ['nullable', 'string', 'max:500'],
            'ldap_bind_dn' => ['nullable', 'string', 'max:500'],
            'ldap_password' => ['nullable', 'string'],
            'ldap_use_tls' => ['boolean'],
        ]);

        $settings = TenantSettings::firstOrCreate([]);

        $data = [
            'ldap_host' => $validated['ldap_host'],
            'ldap_port' => $validated['ldap_port'] ?? 636,
            'ldap_base_dn' => $validated['ldap_base_dn'],
            'ldap_bind_dn' => $validated['ldap_bind_dn'],
            'ldap_use_tls' => $request->boolean('ldap_use_tls'),
        ];

        if ($request->filled('ldap_password')) {
            $data['ldap_bind_password_enc'] = Crypt::encryptString($request->ldap_password);
        }

        $settings->update($data);

        return back()->with('success', 'Configuration LDAP enregistrée.');
    }

    public function testLdap()
    {
        try {
            $settings = TenantSettings::firstOrCreate([]);
            $service = app(\App\Services\LdapAuthService::class);

            if (! $settings->ldap_host) {
                return response()->json(['ok' => false, 'message' => 'LDAP non configuré.']);
            }

            // Test de connexion simple
            $password = Crypt::decryptString($settings->ldap_bind_password_enc);
            $conn = new \LdapRecord\Connection([
                'hosts' => [$settings->ldap_host],
                'port' => $settings->ldap_port ?? 636,
                'base_dn' => $settings->ldap_base_dn,
                'username' => $settings->ldap_bind_dn,
                'password' => $password,
                'use_tls' => (bool) $settings->ldap_use_tls,
                'timeout' => 5,
            ]);
            $conn->connect();

            return response()->json(['ok' => true, 'message' => 'Connexion LDAP réussie.']);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    public function smtp()
    {
        $org = app(\App\Services\TenantManager::class)->current();

        return view('admin.settings.smtp', compact('org'));
    }

    public function updateSmtp(Request $request)
    {
        $validated = $request->validate([
            'smtp_host' => ['nullable', 'string', 'max:255'],
            'smtp_port' => ['nullable', 'integer'],
            'smtp_user' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string'],
            'smtp_from_address' => ['nullable', 'email'],
            'smtp_from_name' => ['nullable', 'string', 'max:255'],
        ]);

        $org = app(\App\Services\TenantManager::class)->current();

        $data = [
            'smtp_host' => $validated['smtp_host'],
            'smtp_port' => $validated['smtp_port'] ?? 587,
            'smtp_user' => $validated['smtp_user'],
            'smtp_from_address' => $validated['smtp_from_address'],
            'smtp_from_name' => $validated['smtp_from_name'],
        ];

        if ($request->filled('smtp_password')) {
            $data['smtp_password_enc'] = Crypt::encryptString($request->smtp_password);
        }

        $org->update($data);

        return back()->with('success', 'Configuration SMTP enregistrée.');
    }

    public function branding()
    {
        $org = app(\App\Services\TenantManager::class)->current();

        return view('admin.settings.branding', compact('org'));
    }

    public function updateBranding(Request $request)
    {
        $validated = $request->validate([
            'primary_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,svg', 'max:2048'],
            'login_bg' => ['nullable', 'image', 'mimes:png,jpg', 'max:4096'],
        ]);

        $org = app(\App\Services\TenantManager::class)->current();

        $data = [];

        if (isset($validated['primary_color'])) {
            $data['primary_color'] = $validated['primary_color'];
        }

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store("orgs/{$org->slug}/branding", 'public');
        }

        if ($request->hasFile('login_bg')) {
            $data['login_bg_path'] = $request->file('login_bg')->store("orgs/{$org->slug}/branding", 'public');
        }

        $org->update($data);

        return back()->with('success', 'Personnalisation sauvegardée.');
    }

    public function media()
    {
        $settings = \App\Models\Tenant\TenantSettings::firstOrCreate([]);
        return view('admin.settings.media', compact('settings'));
    }

    public function updateMedia(Request $request)
    {
        $validated = $request->validate([
            'media_default_cols' => ['required', 'integer', 'in:1,2,3,4,5,6'],
        ]);

        $settings = \App\Models\Tenant\TenantSettings::firstOrCreate([]);
        $settings->update($validated);

        return back()->with('success', 'Paramètres photothèque sauvegardés.');
    }
}
