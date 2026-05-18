<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Platform\PlatformSettings;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use PragmaRX\Google2FA\Google2FA;

class SecurityController extends Controller
{
    /**
     * Tableau de bord sécurité — état GPG, sauvegarde, workers, PHP/Laravel,
     * clés SSH autorisées, dernier test de restauration.
     */
    public function dashboard(): View
    {
        $settings = PlatformSettings::firstOrCreate([]);

        // ── Versions ──────────────────────────────────────────────────
        $phpVersion = PHP_VERSION;
        $laravelVersion = app()->version();

        // ── Workers Supervisor ────────────────────────────────────────
        $workers = $this->getWorkerStatus();

        // ── Clés SSH autorisées ───────────────────────────────────────
        $sshKeys = $this->getSshAuthorizedKeys();

        // ── Espace disque destination sauvegarde ──────────────────────
        $diskInfo = $this->getDiskInfo($settings->backup_local_path);

        return view('super-admin.security-dashboard', compact(
            'settings',
            'phpVersion',
            'laravelVersion',
            'workers',
            'sshKeys',
            'diskInfo',
        ));
    }

    /**
     * Enregistre un test de restauration (date + statut + note).
     */
    public function recordRestoreTest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:success,failed'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $settings = PlatformSettings::firstOrCreate([]);
        $settings->update([
            'security_restore_tested_at' => now(),
            'security_restore_test_status' => $validated['status'],
            'security_restore_test_note' => $validated['note'] ?? null,
        ]);

        return response()->json(['ok' => true, 'message' => 'Test de restauration enregistré.']);
    }

    // =========================================================================
    // Helpers privés
    // =========================================================================

    /**
     * Récupère le statut des workers via supervisorctl.
     *
     * @return array<int, array{name: string, status: string, ok: bool}>
     */
    private function getWorkerStatus(): array
    {
        exec('supervisorctl status 2>/dev/null', $lines, $code);

        if ($code !== 0 || empty($lines)) {
            return [];
        }

        $workers = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $parts = preg_split('/\s+/', trim($line), 3);
            $name = $parts[0] ?? '?';
            $status = $parts[1] ?? '?';
            $workers[] = [
                'name' => $name,
                'status' => $status,
                'ok' => strtoupper($status) === 'RUNNING',
            ];
        }

        return $workers;
    }

    /**
     * Lit les clés SSH autorisées de l'utilisateur courant.
     *
     * @return array<int, string>
     */
    private function getSshAuthorizedKeys(): array
    {
        $paths = [
            '/root/.ssh/authorized_keys',
            posix_getpwuid(posix_geteuid())['dir'].'/.ssh/authorized_keys',
        ];

        foreach ($paths as $path) {
            if (! file_exists($path)) {
                continue;
            }

            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

            return array_values(array_filter($lines, fn ($l) => ! str_starts_with(trim($l), '#')));
        }

        return [];
    }

    /**
     * Retourne des infos sur l'espace disque du dossier de sauvegarde.
     *
     * @return array{path: string, free_h: string, total_h: string, used_pct: int}|null
     */
    private function getDiskInfo(?string $path): ?array
    {
        if (empty($path) || ! is_dir($path)) {
            return null;
        }

        $free = disk_free_space($path);
        $total = disk_total_space($path);

        if ($free === false || $total === false || $total === 0.0) {
            return null;
        }

        $usedPct = (int) round(($total - $free) / $total * 100);

        return [
            'path' => $path,
            'free_h' => $this->humanBytes((int) $free),
            'total_h' => $this->humanBytes((int) $total),
            'used_pct' => $usedPct,
        ];
    }

    private function humanBytes(int $bytes): string
    {
        $units = ['o', 'Ko', 'Mo', 'Go', 'To'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes = (int) ($bytes / 1024);
            $i++;
        }

        return $bytes.' '.$units[$i];
    }

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
