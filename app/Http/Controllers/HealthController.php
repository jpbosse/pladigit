<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function check(Request $request): mixed
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'disk' => $this->checkDisk(),
        ];

        $healthy = collect($checks)->every(fn ($c) => $c['ok']);
        $status = $healthy ? 'ok' : 'degraded';
        $ts = now()->toIso8601String();

        // Appel AJAX, monitoring externe ou ?json=1 → JSON
        if ($request->expectsJson() || $request->boolean('json')) {
            return response()->json([
                'status' => $status,
                'checks' => $checks,
                'ts' => $ts,
            ], $healthy ? 200 : 503);
        }

        // Navigateur → page HTML
        return response(
            view('health', compact('checks', 'status', 'ts', 'healthy'))->render(),
            $healthy ? 200 : 503
        )->header('Content-Type', 'text/html');
    }

    public function ping(): \Illuminate\Http\Response
    {
        return response('OK', 200)->header('Content-Type', 'text/plain');
    }

    private function checkDatabase(): array
    {
        try {
            DB::connection('mysql')->select('SELECT 1');

            return ['ok' => true, 'message' => 'Base de données accessible'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    private function checkRedis(): array
    {
        try {
            $key = '_health_'.uniqid();
            Cache::store('redis')->put($key, 1, 5);
            Cache::store('redis')->forget($key);

            return ['ok' => true, 'message' => 'Cache Redis accessible'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    private function checkDisk(): array
    {
        $path = storage_path();
        $freeBytes = disk_free_space($path);
        $totalBytes = disk_total_space($path);

        if ($freeBytes === false || $totalBytes === false || $totalBytes === 0.0) {
            return ['ok' => false, 'message' => 'Impossible de lire les statistiques disque'];
        }

        $usedBytes = $totalBytes - $freeBytes;
        $freePercent = round(($freeBytes / $totalBytes) * 100, 1);
        $usedPercent = round(100 - $freePercent, 1);
        $ok = $freePercent >= 10;

        return [
            'ok' => $ok,
            'free_percent' => $freePercent,
            'used_percent' => $usedPercent,
            'free_gb' => round($freeBytes / 1_073_741_824, 2),
            'used_gb' => round($usedBytes / 1_073_741_824, 2),
            'total_gb' => round($totalBytes / 1_073_741_824, 2),
            'message' => $ok ? "Disque OK — {$freePercent}% libre" : "Espace disque faible — {$freePercent}% libre",
        ];
    }
}
