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

        // ── Rate limiting : 1 sauvegarde manuelle / 10 min ───────────
        if ($settings->backup_last_run_at !== null
            && $settings->backup_last_run_at->gt(now()->subMinutes(10))
        ) {
            $waitSeconds = (int) now()->diffInSeconds($settings->backup_last_run_at->addMinutes(10));
            $waitMin = (int) ceil($waitSeconds / 60);

            return response()->json([
                'ok' => false,
                'message' => "Une sauvegarde a déjà été lancée récemment. Veuillez patienter encore {$waitMin} minute(s).",
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

    /**
     * Liste les archives locales présentes dans le dossier de destination.
     * Retourne aussi le SHA-256 attendu (contenu du .sha256) si disponible.
     */
    public function listBackups(): JsonResponse
    {
        $settings = PlatformSettings::firstOrCreate([]);

        if (($settings->backup_driver ?? 'local') !== 'local') {
            return response()->json(['ok' => false, 'message' => 'Listage disponible uniquement en mode local.']);
        }

        $destDir = rtrim((string) ($settings->backup_local_path ?? ''), '/');

        if ($destDir === '' || ! is_dir($destDir)) {
            return response()->json(['ok' => true, 'archives' => []]);
        }

        // Les archives sont organisées par sous-dossier : backup_complet/{slug}/backup_*.tar.gz
        $files = array_merge(
            glob($destDir.'/*/backup_*.tar.gz') ?: [],
            glob($destDir.'/*/backup_*.tar.gz.gpg') ?: [],
            // Compatibilité ancienne structure à plat
            glob($destDir.'/backup_*.tar.gz') ?: [],
            glob($destDir.'/backup_*.tar.gz.gpg') ?: []
        );

        // Trier du plus récent au plus ancien
        rsort($files);

        $archives = [];
        foreach ($files as $file) {
            $name = basename($file);
            $size = filesize($file);
            $mtime = filemtime($file);
            $sha256File = $file.'.sha256';
            $sha256 = null;

            if (file_exists($sha256File)) {
                // Format : "<hash>  <filename>\n"
                $line = trim((string) file_get_contents($sha256File));
                $parts = preg_split('/\s+/', $line, 2);
                $sha256 = $parts[0] ?? null;
            }

            $archives[] = [
                'name' => $name,
                'path' => str_replace($destDir.'/', '', $file), // chemin relatif : slug/fichier
                'size' => $size,
                'size_h' => number_format($size / 1024 / 1024, 2).' Mo',
                'date' => date('d/m/Y H:i:s', $mtime),
                'sha256' => $sha256,
                'gpg' => str_ends_with($name, '.gpg'),
            ];
        }

        return response()->json(['ok' => true, 'archives' => $archives]);
    }

    /**
     * Recalcule le SHA-256 d'une archive et le compare à celui du fichier .sha256.
     * GET /super-admin/backup/checksum?file=backup_2026-05-18_demo.tar.gz
     */
    public function checksum(Request $request): JsonResponse
    {
        $request->validate(['file' => ['required', 'string', 'max:255']]);

        $settings = PlatformSettings::firstOrCreate([]);
        $destDir = rtrim((string) ($settings->backup_local_path ?? ''), '/');

        if ($destDir === '') {
            return response()->json(['ok' => false, 'message' => 'Chemin local non configuré.']);
        }

        // Sécurité : autoriser slug/fichier mais interdire toute traversée de chemin (..)
        $relative = ltrim($request->string('file')->toString(), '/');
        if (str_contains($relative, '..')) {
            return response()->json(['ok' => false, 'message' => 'Chemin invalide.']);
        }
        $filePath = $destDir.'/'.$relative;

        if (! file_exists($filePath)) {
            return response()->json(['ok' => false, 'message' => "Fichier introuvable : {$relative}"]);
        }

        $computed = hash_file('sha256', $filePath);

        $sha256File = $filePath.'.sha256';
        $expected = null;

        if (file_exists($sha256File)) {
            $line = trim((string) file_get_contents($sha256File));
            $parts = preg_split('/\s+/', $line, 2);
            $expected = $parts[0] ?? null;
        }

        $match = ($expected !== null && hash_equals($expected, $computed));

        return response()->json([
            'ok' => true,
            'computed' => $computed,
            'expected' => $expected,
            'match' => $match,
        ]);
    }

    /**
     * Page "Tester la sauvegarde" — liste les archives, permet de vérifier
     * SHA-256 et d'inspecter le contenu sans restaurer.
     */
    public function testBackup(): View
    {
        $settings = PlatformSettings::firstOrCreate([]);

        return view('super-admin.backup-test', compact('settings'));
    }

    /**
     * Inspecte une archive : vérifie SHA-256 + liste le contenu tar.
     * Si l'archive est chiffrée GPG, la déchiffre dans un fichier temporaire.
     *
     * GET /super-admin/backup/inspect?file=cedbos/backup_xxx.tar.gz
     */
    public function inspectArchive(Request $request): JsonResponse
    {
        $request->validate(['file' => ['required', 'string', 'max:500']]);

        $settings = PlatformSettings::firstOrCreate([]);
        $destDir = rtrim((string) ($settings->backup_local_path ?? ''), '/');

        if ($destDir === '') {
            return response()->json(['ok' => false, 'message' => 'Chemin local non configuré.']);
        }

        $relative = ltrim($request->string('file')->toString(), '/');
        if (str_contains($relative, '..')) {
            return response()->json(['ok' => false, 'message' => 'Chemin invalide.']);
        }

        $filePath = $destDir.'/'.$relative;

        if (! file_exists($filePath)) {
            return response()->json(['ok' => false, 'message' => "Fichier introuvable : {$relative}"]);
        }

        // ── 1. Vérification SHA-256 ───────────────────────────────────
        $computed = hash_file('sha256', $filePath);
        $sha256File = $filePath.'.sha256';
        $expected = null;
        $sha256Match = null;

        if (file_exists($sha256File)) {
            $line = trim((string) file_get_contents($sha256File));
            $parts = preg_split('/\s+/', $line, 2);
            $expected = $parts[0] ?? null;
            $sha256Match = $expected !== null && hash_equals($expected, $computed);
        }

        // ── 2. Déchiffrement GPG si nécessaire ───────────────────────
        $archiveToInspect = $filePath;
        $tmpDecrypted = null;

        if (str_ends_with($filePath, '.gpg')) {
            if (empty($settings->backup_gpg_passphrase_enc)) {
                return response()->json(['ok' => false, 'message' => 'Archive GPG mais passphrase non configurée.']);
            }

            try {
                $passphrase = Crypt::decryptString((string) $settings->backup_gpg_passphrase_enc);
                $tmpDecrypted = sys_get_temp_dir().'/pladigit_inspect_'.uniqid().'.tar.gz';

                $cmd = sprintf(
                    'gpg --batch --yes --decrypt --passphrase %s --output %s %s 2>/dev/null',
                    escapeshellarg($passphrase),
                    escapeshellarg($tmpDecrypted),
                    escapeshellarg($filePath)
                );
                exec($cmd, $out, $code);

                if ($code !== 0 || ! file_exists($tmpDecrypted)) {
                    return response()->json(['ok' => false, 'message' => 'Déchiffrement GPG échoué.']);
                }

                $archiveToInspect = $tmpDecrypted;
            } catch (\Throwable $e) {
                return response()->json(['ok' => false, 'message' => 'Erreur GPG : '.$e->getMessage()]);
            }
        }

        // ── 3. Listage du contenu tar ─────────────────────────────────
        $entries = [];
        $tarError = null;

        try {
            $cmd = sprintf('tar -tzf %s 2>&1', escapeshellarg($archiveToInspect));
            exec($cmd, $lines, $tarCode);

            if ($tarCode !== 0) {
                $tarError = implode(' ', array_slice($lines, 0, 3));
            } else {
                // Dédoublonner et trier, ignorer les entrées de répertoire seules
                $entries = array_values(array_filter($lines, fn ($l) => ! str_ends_with($l, '/')));
                sort($entries);
            }
        } finally {
            // Nettoyer le fichier temporaire déchiffré
            if ($tmpDecrypted && file_exists($tmpDecrypted)) {
                @unlink($tmpDecrypted);
            }
        }

        return response()->json([
            'ok' => true,
            'file' => basename($filePath),
            'sha256' => [
                'computed' => $computed,
                'expected' => $expected,
                'match' => $sha256Match,
            ],
            'gpg_decrypted' => $tmpDecrypted !== null,
            'entries' => $entries,
            'entry_count' => count($entries),
            'tar_error' => $tarError,
        ]);
    }
}
