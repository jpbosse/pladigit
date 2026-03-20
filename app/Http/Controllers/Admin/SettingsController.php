<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\TenantSettings;
use App\Models\Tenant\User;
use App\Services\MediaService;
use App\Services\TenantManager;
use Illuminate\Http\JsonResponse;
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
            'ldap_use_ssl' => ['boolean'],
            'ldap_use_tls' => ['boolean'],
        ]);

        $settings = TenantSettings::firstOrCreate([]);

        $data = [
            'ldap_host' => $validated['ldap_host'],
            'ldap_port' => $validated['ldap_port'] ?? 636,
            'ldap_base_dn' => $validated['ldap_base_dn'],
            'ldap_bind_dn' => $validated['ldap_bind_dn'],
            'ldap_use_ssl' => $request->boolean('ldap_use_ssl'),
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
            $settings = TenantSettings::firstOrCreate([])->fresh();

            if (! $settings->ldap_host) {
                return response()->json(['ok' => false, 'message' => 'LDAP non configuré.']);
            }

            if (! $settings->ldap_bind_password_enc) {
                return response()->json(['ok' => false, 'message' => 'Mot de passe LDAP non enregistré — veuillez le saisir et sauvegarder avant de tester.']);
            }

            $password = Crypt::decryptString($settings->ldap_bind_password_enc);
            $conn = new \LdapRecord\Connection([
                'hosts' => [$settings->ldap_host],
                'port' => $settings->ldap_port ?? 636,
                'base_dn' => $settings->ldap_base_dn,
                'username' => $settings->ldap_bind_dn,
                'password' => $password,
                'use_ssl' => (bool) $settings->ldap_use_ssl,
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
            'smtp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_encryption' => ['nullable', 'in:tls,smtps,none'],
            'smtp_user' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string'],
            'smtp_from_address' => ['nullable', 'email'],
            'smtp_from_name' => ['nullable', 'string', 'max:255'],
        ]);

        $org = app(\App\Services\TenantManager::class)->current();

        $data = [
            'smtp_host' => $validated['smtp_host'],
            'smtp_port' => $validated['smtp_port'] ?? 587,
            'smtp_encryption' => $validated['smtp_encryption'] ?? 'tls',
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

    public function testSmtp()
    {
        try {
            // fresh() force un rechargement depuis la base — évite de tester
            // avec une instance Eloquent chargée avant la dernière sauvegarde.
            $org = app(\App\Services\TenantManager::class)->current()?->fresh();
            $mailer = app(\App\Services\TenantMailer::class);

            if (! $org || ! $mailer->isConfigured($org)) {
                return response()->json(['ok' => false, 'message' => 'SMTP non configuré sur cette organisation.']);
            }

            $mailer->configureForTenant($org);

            \Mail::raw('Test de connexion SMTP — Pladigit', function ($msg) use ($org) {
                $msg->to($org->smtp_from_address ?: config('mail.from.address'))
                    ->subject('Test SMTP Pladigit — '.$org->name);
            });

            return response()->json(['ok' => true, 'message' => 'Email de test envoyé avec succès.']);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }
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
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,svg', 'max:2048'],
            'login_bg' => ['nullable', 'file', 'mimes:png,jpg,jpeg', 'max:4096'],
        ]);

        $org = app(\App\Services\TenantManager::class)->current();
        $disk = \Storage::disk('public');
        $data = [];

        if (filled($validated['primary_color'] ?? null)) {
            $data['primary_color'] = $validated['primary_color'];
        }

        // Logo
        if ($request->boolean('remove_logo') && $org->logo_path) {
            $disk->delete($org->logo_path);
            $data['logo_path'] = null;
        } elseif ($request->hasFile('logo') && $request->file('logo')->isValid()) {
            if ($org->logo_path) {
                $disk->delete($org->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store("orgs/{$org->slug}/branding", 'public');
        }

        // Fond login
        if ($request->boolean('remove_login_bg') && $org->login_bg_path) {
            $disk->delete($org->login_bg_path);
            $data['login_bg_path'] = null;
        } elseif ($request->hasFile('login_bg') && $request->file('login_bg')->isValid()) {
            if ($org->login_bg_path) {
                $disk->delete($org->login_bg_path);
            }
            $data['login_bg_path'] = $request->file('login_bg')->store("orgs/{$org->slug}/branding", 'public');
        }

        if (! empty($data)) {
            $org->update($data);
        }

        return back()->with('success', 'Personnalisation sauvegardée.');
    }

    public function media()
    {
        $settings = TenantSettings::firstOrCreate([]);

        return view('admin.settings.media', compact('settings'));
    }

    public function updateMedia(Request $request)
    {
        $validated = $request->validate([
            'media_default_cols' => ['required', 'integer', 'min:1', 'max:12'],
            // Watermark
            'wm_enabled' => ['sometimes', 'boolean'],
            'wm_type' => ['sometimes', 'in:text,logo'],
            'wm_text' => ['nullable', 'string', 'max:100'],
            'wm_position' => ['sometimes', 'in:bottom-right,bottom-left,center,bottom-center'],
            'wm_opacity' => ['sometimes', 'integer', 'min:10', 'max:100'],
            'wm_size' => ['sometimes', 'in:small,medium,large'],
        ]);

        // La checkbox wm_enabled n'est pas soumise si décochée
        $validated['wm_enabled'] = $request->boolean('wm_enabled');

        $settings = TenantSettings::firstOrCreate([]);
        $settings->update($validated);

        return back()->with('success', 'Paramètres photothèque sauvegardés.');
    }

    public function nas()
    {
        $settings = TenantSettings::firstOrCreate([]);

        return view('admin.settings.nas', compact('settings'));
    }

    public function updateNas(Request $request)
    {
        $validated = $request->validate([
            'nas_photo_driver' => ['required', 'in:local,sftp,smb'],
            'nas_photo_local_path' => ['nullable', 'string', 'max:500'],
            'nas_photo_host' => ['nullable', 'string', 'max:255'],
            'nas_photo_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'nas_photo_username' => ['nullable', 'string', 'max:255'],
            'nas_photo_password' => ['nullable', 'string', 'max:255'],
            'nas_photo_root_path' => ['nullable', 'string', 'max:500'],
            'nas_photo_sync_interval_minutes' => ['required', 'integer', 'min:15', 'max:1440'],
        ]);

        $settings = TenantSettings::firstOrCreate([]);
        $data = collect($validated)->except('nas_photo_password')->toArray();

        if (filled($request->nas_photo_password)) {
            $data['nas_photo_password_enc'] = Crypt::encryptString($request->nas_photo_password);
        }

        $settings->update($data);

        return back()->with('success', 'Configuration NAS sauvegardée.');
    }

    public function security()
    {
        $settings = TenantSettings::firstOrCreate([]);

        return view('admin.settings.security', compact('settings'));
    }

    public function updateSecurity(Request $request)
    {
        $validated = $request->validate([
            'session_lifetime_minutes' => ['required', 'integer', 'min:5', 'max:10080'],
            'login_max_attempts' => ['required', 'integer', 'min:3', 'max:20'],
            'login_lockout_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
        ]);

        $settings = TenantSettings::firstOrCreate([]);
        $settings->update($validated);

        return back()->with('success', 'Paramètres de sécurité enregistrés.');
    }

    public function syncNas(Request $request, MediaService $mediaService, TenantManager $tenantManager): JsonResponse
    {
        $deep = (bool) $request->input('deep', false);

        try {
            $owner = User::where('role', 'admin')->first();

            $result = $mediaService->syncAlbumTree(
                nasRoot: '',
                owner: $owner,
                deep: $deep,
            );

            // Mise à jour de la dernière sync
            TenantSettings::firstOrCreate([])->update(['nas_photo_last_sync_at' => now()]);

            $parts = [];
            if ($result['files_added'] > 0) {
                $parts[] = $result['files_added'].' fichier(s) ajouté(s)';
            }
            if ($result['files_removed'] > 0) {
                $parts[] = $result['files_removed'].' fichier(s) supprimé(s)';
            }
            if ($result['albums_created'] > 0) {
                $parts[] = $result['albums_created'].' album(s) créé(s)';
            }
            if ($result['albums_removed'] > 0) {
                $parts[] = $result['albums_removed'].' album(s) supprimé(s)';
            }
            if ($result['errors'] > 0) {
                $parts[] = $result['errors'].' erreur(s)';
            }

            $message = empty($parts) ? 'Aucune modification détectée.' : implode(', ', $parts).'.';

            return response()->json([
                'ok' => $result['errors'] === 0,
                'message' => $message,
                'stats' => $result,
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Erreur : '.$e->getMessage(),
            ], 500);
        }
    }

    public function visio()
    {
        $settings = \App\Models\Tenant\TenantSettings::on('tenant')->firstOrCreate([]);

        return view('admin.settings.visio', compact('settings'));
    }

    public function updateVisio(Request $request)
    {
        $validated = $request->validate([
            'jitsi_base_url' => ['required', 'url', 'max:255'],
        ]);

        $settings = \App\Models\Tenant\TenantSettings::on('tenant')->firstOrCreate([]);
        $settings->update($validated);

        return back()->with('success', 'Paramètres visio enregistrés.');
    }
}
