<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Platform\PlatformSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class UpdateController extends Controller
{
    private const SCRIPT = '/var/www/pladigit/install/update.sh';

    private const ROOT_DIR = '/var/www/pladigit';

    private const LOG_DIR = '/var/www/pladigit/storage/logs/updates';

    private const GITHUB_TAGS_URL = 'https://api.github.com/repos/jpbosse/pladigit/tags';

    public function index(): View
    {
        $settings = PlatformSettings::firstOrCreate([]);

        return view('super-admin.update', [
            'settings' => $settings,
            'currentVersion' => config('app.version'),
        ]);
    }

    public function run(): JsonResponse
    {
        $settings = PlatformSettings::firstOrCreate([]);

        if ($settings->update_last_status === 'running') {
            return response()->json([
                'ok' => false,
                'message' => 'Une mise à jour est déjà en cours.',
            ]);
        }

        $logFile = self::LOG_DIR.'/update_'.now()->format('Y-m-d_His').'.log';

        $settings->update([
            'update_last_status' => 'running',
            'update_last_message' => 'Mise à jour démarrée…',
            'update_last_run_at' => now(),
            'update_current_version' => config('app.version'),
            'update_log_path' => $logFile,
        ]);

        // Lancement en arrière-plan : nohup garantit que le processus survit à la fin de la requête
        $cmd = sprintf(
            'nohup sudo %s %s %s >%s 2>&1 &',
            escapeshellarg(self::SCRIPT),
            escapeshellarg($logFile),
            escapeshellarg(self::ROOT_DIR),
            escapeshellarg($logFile.'.nohup')
        );
        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            $settings->update([
                'update_last_status' => 'error',
                'update_last_message' => 'Impossible de démarrer le processus de mise à jour.',
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Impossible de démarrer le processus de mise à jour.',
            ]);
        }

        $settings->update(['update_last_message' => 'Mise à jour en cours…']);

        return response()->json([
            'ok' => true,
            'message' => 'Mise à jour lancée en arrière-plan. Suivez la progression ci-dessous.',
        ]);
    }

    public function status(): JsonResponse
    {
        $settings = PlatformSettings::firstOrCreate([]);

        return response()->json([
            'status' => $settings->update_last_status,
            'message' => $settings->update_last_message,
            'last_run' => $settings->update_last_run_at?->format('d/m/Y à H:i:s'),
            'current_version' => $settings->update_current_version,
            'available_version' => $settings->update_available_version,
        ]);
    }

    public function log(): JsonResponse
    {
        $settings = PlatformSettings::firstOrCreate([]);
        $logPath = $settings->update_log_path;
        $offset = (int) request()->query('offset', 0);

        if (! $logPath || ! str_starts_with($logPath, self::LOG_DIR)) {
            return response()->json(['lines' => [], 'offset' => 0]);
        }

        if (! file_exists($logPath)) {
            return response()->json(['lines' => [], 'offset' => 0]);
        }

        $content = file_get_contents($logPath, false, null, $offset);
        if ($content === false) {
            return response()->json(['lines' => [], 'offset' => $offset]);
        }

        $lines = array_values(array_filter(explode("\n", $content)));

        return response()->json([
            'lines' => $lines,
            'offset' => $offset + strlen($content),
        ]);
    }

    public function checkVersion(): JsonResponse
    {
        try {
            $response = Http::timeout(5)
                ->withHeaders(['User-Agent' => 'Pladigit/'.config('app.version')])
                ->get(self::GITHUB_TAGS_URL);

            if (! $response->successful()) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Impossible de contacter GitHub (HTTP '.$response->status().').',
                ]);
            }

            $tags = $response->json();

            if (empty($tags)) {
                return response()->json([
                    'ok' => true,
                    'version' => null,
                    'message' => 'Aucun tag trouvé sur le dépôt.',
                ]);
            }

            $latest = ltrim($tags[0]['name'] ?? '', 'v');

            $settings = PlatformSettings::firstOrCreate([]);
            $settings->update(['update_available_version' => $latest]);

            return response()->json([
                'ok' => true,
                'version' => $latest,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Erreur réseau : '.$e->getMessage(),
            ]);
        }
    }
}
