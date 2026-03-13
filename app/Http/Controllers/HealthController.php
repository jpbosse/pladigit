<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function check(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'disk' => $this->checkDisk(),
        ];

        $healthy = collect($checks)->every(fn ($c) => $c['ok']);

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => $checks,
            'ts' => now()->toIso8601String(),
        ], $healthy ? 200 : 503);
    }

    public function ping(): \Illuminate\Http\Response
    {
        return response('OK', 200)->header('Content-Type', 'text/plain');
    }

    private function checkDatabase(): array
    {
        try {
            DB::connection('mysql')->select('SELECT 1');

            return ['ok' => true, 'message' => 'platform DB reachable'];
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

            return ['ok' => true, 'message' => 'Redis reachable'];
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
            return ['ok' => false, 'message' => 'Unable to read disk stats'];
        }

        $freePercent = round(($freeBytes / $totalBytes) * 100, 1);
        $ok = $freePercent >= 10;

        return [
            'ok' => $ok,
            'free_percent' => $freePercent,
            'free_gb' => round($freeBytes / 1_073_741_824, 2),
            'message' => $ok ? "Disk OK ({$freePercent}% free)" : "Low disk space ({$freePercent}% free)",
        ];
    }
}
