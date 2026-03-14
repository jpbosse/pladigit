<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Platform\Organization;
use App\Services\TenantManager;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    public function index()
    {
        $orgs = Organization::orderBy('name')->get();
        $manager = app(TenantManager::class);
        $rows = [];

        foreach ($orgs as $org) {
            try {
                $manager->connectTo($org);

                $userCount = (int) DB::connection('tenant')->table('users')->count();

                // Photothèque
                $bytesMedia = 0;
                try {
                    $bytesMedia = (int) DB::connection('tenant')
                        ->table('media_items')
                        ->whereNull('deleted_at')
                        ->sum('file_size_bytes');
                } catch (\Throwable) {
                }

                // GED (Phase 5)
                $bytesGed = 0;
                try {
                    $bytesGed = (int) DB::connection('tenant')
                        ->table('documents')
                        ->whereNull('deleted_at')
                        ->sum('file_size_bytes');
                } catch (\Throwable) {
                }

                // Chat — estimé 200 Ko par message avec pièce jointe (Phase 9)
                $bytesChat = 0;
                try {
                    $chatFiles = (int) DB::connection('tenant')
                        ->table('chat_messages')
                        ->whereNotNull('attachments')
                        ->whereRaw('JSON_LENGTH(attachments) > 0')
                        ->count();
                    $bytesChat = $chatFiles * 200 * 1024;
                } catch (\Throwable) {
                }

                $totalBytes = $bytesMedia + $bytesGed + $bytesChat;
                $quotaMb = $org->storage_quota_mb ?? 10240;
                $quotaPct = $quotaMb > 0
                    ? min(100, (int) round($totalBytes / 1024 / 1024 / $quotaMb * 100))
                    : 0;

                $rows[] = [
                    'id' => $org->id,
                    'name' => $org->name,
                    'slug' => $org->slug,
                    'plan' => $org->plan ?? 'communautaire',
                    'status' => $org->status,
                    'user_count' => $userCount,
                    'bytes_media' => $bytesMedia,
                    'bytes_ged' => $bytesGed,
                    'bytes_chat' => $bytesChat,
                    'total_bytes' => $totalBytes,
                    'total_mb' => round($totalBytes / 1024 / 1024, 1),
                    'quota_mb' => $quotaMb,
                    'quota_pct' => $quotaPct,
                ];
            } catch (\Throwable) {
                $rows[] = [
                    'id' => $org->id,
                    'name' => $org->name,
                    'slug' => $org->slug,
                    'plan' => $org->plan ?? 'communautaire',
                    'status' => $org->status,
                    'user_count' => 0,
                    'bytes_media' => 0,
                    'bytes_ged' => 0,
                    'bytes_chat' => 0,
                    'total_bytes' => 0,
                    'total_mb' => 0.0,
                    'quota_mb' => $org->storage_quota_mb ?? 10240,
                    'quota_pct' => 0,
                ];
            }
        }

        // Totaux plateforme
        $totals = [
            'orgs' => count($rows),
            'users' => array_sum(array_column($rows, 'user_count')),
            'bytes_media' => array_sum(array_column($rows, 'bytes_media')),
            'bytes_ged' => array_sum(array_column($rows, 'bytes_ged')),
            'bytes_chat' => array_sum(array_column($rows, 'bytes_chat')),
            'total_bytes' => array_sum(array_column($rows, 'total_bytes')),
        ];

        return view('super-admin.stats', compact('rows', 'totals'));
    }
}
