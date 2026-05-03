<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Jobs\PlatformBackupJob;
use App\Models\Platform\Organization;
use App\Models\Platform\PlatformSettings;
use App\Models\Tenant\TenantSettings;
use App\Services\BackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\View\View;

class BackupController extends Controller
{
    public function index(): View
    {
        $settings = PlatformSettings::firstOrCreate([]);
        $orgs = Organization::where('status', 'active')->orderBy('name')->get(['id', 'name', 'slug', 'db_name']);

        return view('super-admin.backup', compact('settings', 'orgs'));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'backup_enabled' => ['boolean'],
            'backup_schedule' => ['required', 'in:hourly,daily,weekly'],
            'backup_driver' => ['required', 'in:local,sftp'],
            'backup_local_path' => ['nullable', 'string', 'max:500'],
            'backup_sftp_host' => ['nullable', 'string', 'max:255'],
            'backup_sftp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'backup_sftp_user' => ['nullable', 'string', 'max:255'],
            'backup_sftp_password' => ['nullable', 'string', 'max:255'],
            'backup_sftp_path' => ['nullable', 'string', 'max:500'],
            'backup_retention_count' => ['required', 'integer', 'min:1', 'max:90'],
        ]);

        $settings = PlatformSettings::firstOrCreate([]);
        $data = collect($validated)->except('backup_sftp_password')->toArray();
        $data['backup_enabled'] = $request->boolean('backup_enabled');

        if (filled($request->backup_sftp_password)) {
            $data['backup_sftp_password_enc'] = Crypt::encryptString($request->backup_sftp_password);
        }

        $settings->update($data);

        return back()->with('success', 'Configuration sauvegarde enregistrée.');
    }

    public function run(): JsonResponse
    {
        $settings = PlatformSettings::firstOrCreate([]);

        if (! $settings->backupIsConfigured()) {
            return response()->json([
                'ok' => false,
                'message' => 'La destination de sauvegarde n\'est pas configurée.',
            ]);
        }

        PlatformBackupJob::dispatch();

        return response()->json([
            'ok' => true,
            'message' => 'Sauvegarde de toutes les organisations lancée en arrière-plan.',
        ]);
    }

    public function status(): JsonResponse
    {
        $settings = PlatformSettings::firstOrCreate([]);

        return response()->json([
            'status' => $settings->backup_last_status,
            'message' => $settings->backup_last_message,
            'last_run' => $settings->backup_last_run_at?->format('d/m/Y à H:i:s'),
            'size' => $settings->backupHumanSize(),
        ]);
    }

    public function testSftp(BackupService $backupService): JsonResponse
    {
        $settings = PlatformSettings::firstOrCreate([]);

        // Proxy vers TenantSettings pour réutiliser BackupService::testSftp()
        $proxy = new TenantSettings;
        $proxy->forceFill([
            'backup_sftp_host' => $settings->backup_sftp_host,
            'backup_sftp_port' => $settings->backup_sftp_port,
            'backup_sftp_user' => $settings->backup_sftp_user,
            'backup_sftp_password_enc' => $settings->backup_sftp_password_enc,
        ]);

        return response()->json($backupService->testSftp($proxy));
    }
}
