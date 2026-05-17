<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\ModuleKey;
use App\Http\Controllers\Controller;
use App\Models\Platform\Organization;
use App\Models\Tenant\TenantSettings;
use App\Models\Tenant\User;
use App\Services\ProvisioningException;
use App\Services\TenantMailer;
use App\Services\TenantManager;
use App\Services\TenantProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use LdapRecord\Connection;

class OrganizationController extends Controller
{
    public function __construct(
        private TenantProvisioningService $provisioning,
        private TenantManager $tenantManager,
    ) {}

    public function index()
    {
        $orgs = Organization::orderBy('name')->paginate(25);

        return view('super-admin.organizations.index', compact('orgs'));
    }

    public function create()
    {
        return view('super-admin.organizations.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'alpha_dash', 'unique:organizations'],
            'plan' => ['required', 'in:communautaire,partenaire'],
            'storage_quota_mb' => ['nullable', 'integer', 'min:512'],
        ]);
        $validated['db_name'] = Organization::dbNameFromSlug($validated['slug']);
        $validated['max_users'] = $this->maxUsersFromPlan($validated['plan']);
        $validated['storage_quota_mb'] = $validated['storage_quota_mb'] ?? 10240;
        $org = Organization::create($validated);

        try {
            $this->provisioning->provisionTenant($org);
        } catch (ProvisioningException $e) {
            // L'org est créée mais reste en 'pending' — la DB a été supprimée
            $org->delete();

            return redirect()
                ->route('super-admin.organizations.index')
                ->with('error', 'Échec du provisioning : '.$e->getMessage().
                    ' L\'organisation a été supprimée. Vérifiez les droits MySQL et réessayez.');
        }

        return redirect()
            ->route('super-admin.organizations.show', $org)
            ->with('success', "Organisation {$org->name} créée. Créez maintenant le premier administrateur.");
    }

    public function show(Organization $organization)
    {
        $userCount = 0;
        $ldapSettings = null;
        try {
            $this->tenantManager->connectTo($organization);
            $userCount = \DB::connection('tenant')->table('users')->count();
            $ldapSettings = TenantSettings::first();
        } catch (\Throwable) {
        }

        return view('super-admin.organizations.show', compact('organization', 'userCount', 'ldapSettings'));
    }

    public function edit(Organization $organization)
    {
        // Connexion au tenant pour lire le stockage utilisé
        $usedMb = 0.0;
        try {
            $this->tenantManager->connectTo($organization);
            $usedBytes = (int) \DB::connection('tenant')
                ->table('media_items')
                ->whereNull('deleted_at')
                ->sum('file_size_bytes');
            $usedMb = round($usedBytes / 1048576, 1);
        } catch (\Throwable) {
        }

        $diskFreeGb = round(disk_free_space('/') / 1073741824, 1);

        return view('super-admin.organizations.edit', compact('organization', 'usedMb', 'diskFreeGb'));
    }

    public function update(Request $request, Organization $organization)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'plan' => ['required', 'in:communautaire,partenaire'],
            'status' => ['required', 'in:active,suspended,pending'],
            'max_users' => ['nullable', 'integer', 'min:1'],
            'storage_quota_mb' => ['nullable', 'integer', 'min:512'],  // 512 Mo minimum
        ]);
        $validated['max_users'] = $validated['max_users'] ?? $this->maxUsersFromPlan($validated['plan']);
        $organization->update($validated);

        return redirect()
            ->route('super-admin.organizations.show', $organization)
            ->with('success', 'Organisation mise à jour.');
    }

    public function suspend(Organization $organization)
    {
        $organization->update(['status' => 'suspended']);

        return back()->with('success', 'Organisation suspendue.');
    }

    public function activate(Organization $organization)
    {
        $organization->update(['status' => 'active']);

        return back()->with('success', 'Organisation réactivée.');
    }

    public function createAdmin(Request $request, Organization $organization)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'password' => ['required', 'min:8'],
        ]);

        $this->tenantManager->connectTo($organization);

        User::updateOrCreate(
            ['email' => $request->email],
            [
                'name' => $request->name,
                'password_hash' => Hash::make($request->password),
                'role' => 'admin',
                'status' => 'active',
            ]
        );

        return redirect()
            ->route('super-admin.organizations.show', $organization)
            ->with('success', "Administrateur {$request->email} créé.");
    }

    public function updateSmtp(Request $request, Organization $organization)
    {
        $validated = $request->validate([
            'smtp_host' => ['nullable', 'string', 'max:255'],
            'smtp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_encryption' => ['nullable', 'in:tls,smtps,none'],
            'smtp_user' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:255'],
            'smtp_from_address' => ['nullable', 'email', 'max:255'],
            'smtp_from_name' => ['nullable', 'string', 'max:255'],
        ]);

        $data = collect($validated)->except('smtp_password')->toArray();

        if (filled($request->smtp_password)) {
            $data['smtp_password_enc'] = Crypt::encryptString($request->smtp_password);
        }

        $organization->update($data);

        return redirect()
            ->route('super-admin.organizations.show', $organization)
            ->with('success', 'Configuration SMTP sauvegardée.');
    }

    public function testSmtp(Organization $organization)
    {
        try {
            $org = $organization->fresh();
            $mailer = app(TenantMailer::class);

            if (! $mailer->isConfigured($org)) {
                return response()->json(['ok' => false, 'message' => 'SMTP non configuré sur cette organisation.']);
            }

            $mailer->configureForTenant($org);

            \Mail::raw('Test de connexion SMTP — Pladigit (Super Admin)', function ($msg) use ($org) {
                $msg->to($org->smtp_from_address ?: config('mail.from.address'))
                    ->subject('Test SMTP Pladigit — '.$org->name);
            });

            return response()->json(['ok' => true, 'message' => 'Email de test envoyé avec succès.']);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    public function testLdap(Organization $organization)
    {
        try {
            $this->tenantManager->connectTo($organization);
            $settings = TenantSettings::first()?->fresh();

            if (! $settings || ! $settings->ldap_host) {
                return response()->json(['ok' => false, 'message' => 'LDAP non configuré sur cette organisation.']);
            }

            if (! $settings->ldap_bind_password_enc) {
                return response()->json(['ok' => false, 'message' => 'Mot de passe LDAP non enregistré — veuillez le saisir et sauvegarder avant de tester.']);
            }

            $password = Crypt::decryptString($settings->ldap_bind_password_enc);
            $conn = new Connection([
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

    public function updateLdap(Request $request, Organization $organization)
    {
        $validated = $request->validate([
            'ldap_host' => ['nullable', 'string', 'max:255'],
            'ldap_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'ldap_base_dn' => ['nullable', 'string', 'max:500'],
            'ldap_bind_dn' => ['nullable', 'string', 'max:500'],
            'ldap_bind_password' => ['nullable', 'string', 'max:255'],
            'ldap_use_ssl' => ['boolean'],
            'ldap_use_tls' => ['boolean'],
            'ldap_sync_interval_hours' => ['nullable', 'integer', 'min:1', 'max:168'],
        ]);

        $this->tenantManager->connectTo($organization);

        $settings = TenantSettings::first() ?? new TenantSettings;

        $data = collect($validated)->except('ldap_bind_password')->toArray();

        if (filled($request->ldap_bind_password)) {
            $data['ldap_bind_password_enc'] = Crypt::encryptString($request->ldap_bind_password);
        }

        $settings->fill($data)->save();

        return redirect()
            ->route('super-admin.organizations.show', $organization)
            ->with('success', 'Configuration LDAP sauvegardée.');
    }

    public function updateModules(Request $request, Organization $organization): RedirectResponse
    {
        $validKeys = ModuleKey::values();

        $submitted = array_filter(
            (array) $request->input('modules', []),
            fn (string $key) => in_array($key, $validKeys, strict: true)
        );

        $organization->update([
            'enabled_modules' => array_values($submitted),
        ]);

        return redirect()
            ->route('super-admin.organizations.show', $organization)
            ->with('success', 'Modules mis à jour.');
    }

    public function destroy(Organization $organization)
    {
        $dbName = $organization->db_name;
        $slug = $organization->slug;
        $orgName = $organization->name;
        $errors = [];

        // 1. Supprimer la base de données tenant
        try {
            \DB::statement('DROP DATABASE IF EXISTS `'.str_replace('`', '', $dbName).'`');
        } catch (\Throwable $e) {
            return redirect()
                ->route('super-admin.organizations.show', $organization)
                ->with('error', 'Impossible de supprimer la base de données : '.$e->getMessage());
        }

        // 2. Supprimer les fichiers GED du tenant
        $gedPath = storage_path('app/private/ged/organisations/'.trim($slug, '/'));
        if (is_dir($gedPath)) {
            try {
                $this->deleteDirectory($gedPath);
            } catch (\Throwable $e) {
                $errors[] = 'GED : '.$e->getMessage();
                \Log::warning("destroy tenant [{$slug}] — échec suppression GED.", [
                    'path' => $gedPath,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 3. Supprimer les sauvegardes locales du tenant
        //    On cherche dans les destinations locales connues
        $backupPaths = [
            storage_path('app/private/backups/'.$slug),
            '/var/backups/pladigit/'.$slug,
        ];
        foreach ($backupPaths as $backupPath) {
            if (is_dir($backupPath)) {
                try {
                    $this->deleteDirectory($backupPath);
                } catch (\Throwable $e) {
                    $errors[] = 'Sauvegardes locales : '.$e->getMessage();
                    \Log::warning("destroy tenant [{$slug}] — échec suppression sauvegardes.", [
                        'path' => $backupPath,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // 4. Log de traçabilité RGPD
        \Log::info("Tenant [{$slug}] supprimé définitivement (RGPD).", [
            'org_name' => $orgName,
            'db_name' => $dbName,
            'ged_path' => $gedPath,
            'errors' => $errors,
            'deleted_by' => 'super-admin',
            'deleted_at' => now()->toIso8601String(),
        ]);

        // 5. Supprimer l'organisation (soft delete)
        $organization->delete();

        $message = "Organisation « {$orgName} » supprimée définitivement (base, GED, sauvegardes locales).";
        if ($errors) {
            $message .= ' Avertissements : '.implode(' | ', $errors);
        }

        return redirect()
            ->route('super-admin.organizations.index')
            ->with($errors ? 'warning' : 'success', $message);
    }

    /**
     * Supprime récursivement un répertoire et son contenu.
     */
    private function deleteDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getRealPath());
            } else {
                unlink($item->getRealPath());
            }
        }

        rmdir($path);
    }

    private function maxUsersFromPlan(string $plan): int
    {
        return 9999;
    }
}
