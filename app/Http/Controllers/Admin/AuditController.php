<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\AuditLog;
use App\Models\Tenant\TenantSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditController extends Controller
{
    // ── Journal (existant enrichi) ────────────────────────────────────────

    public function index(Request $request)
    {
        $query = AuditLog::on('tenant')->orderByDesc('created_at');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('user_name', 'like', "%{$search}%")
                    ->orWhere('action', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        if ($action = $request->input('action')) {
            $query->where('action', $action);
        }

        if ($user = $request->input('user')) {
            $query->where('user_name', 'like', "%{$user}%");
        }

        if ($from = $request->input('from')) {
            $query->where('created_at', '>=', $from.' 00:00:00');
        }

        if ($to = $request->input('to')) {
            $query->where('created_at', '<=', $to.' 23:59:59');
        }

        $logs = $query->paginate(50)->withQueryString();

        $actions = AuditLog::on('tenant')
            ->selectRaw('action, count(*) as cnt')
            ->groupBy('action')
            ->orderByDesc('cnt')
            ->pluck('cnt', 'action');

        $settings = TenantSettings::on('tenant')->first();
        $retention = $settings->audit_retention_months ?? 12;
        $totalLogs = AuditLog::on('tenant')->count();
        $oldestLog = AuditLog::on('tenant')->orderBy('created_at')->value('created_at');

        return view('admin.audit.index', compact(
            'logs', 'actions', 'settings', 'retention', 'totalLogs', 'oldestLog'
        ));
    }

    // ── Statistiques ─────────────────────────────────────────────────────

    public function stats(Request $request)
    {
        // Volume par jour (30 derniers jours)
        $dailyVolume = DB::connection('tenant')
            ->table('audit_logs')
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(created_at) as day, COUNT(*) as cnt')
            ->groupBy('day')
            ->orderBy('day')

            ->pluck('cnt', 'day');

        // Top actions
        $topActions = DB::connection('tenant')
            ->table('audit_logs')
            ->selectRaw('action, COUNT(*) as cnt')
            ->groupBy('action')
            ->orderByDesc('cnt')
            ->limit(10)
            ->get();

        // Top utilisateurs
        $topUsers = DB::connection('tenant')
            ->table('audit_logs')
            ->whereNotNull('user_name')
            ->selectRaw('user_name, COUNT(*) as cnt')
            ->groupBy('user_name')
            ->orderByDesc('cnt')
            ->limit(10)
            ->get();

        // Volume par mois (12 derniers mois)
        $monthlyVolume = DB::connection('tenant')
            ->table('audit_logs')
            ->where('created_at', '>=', now()->subMonths(12))
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as cnt")
            ->groupBy('month')
            ->orderBy('month')

            ->pluck('cnt', 'month');

        $totalLogs = AuditLog::on('tenant')->count();
        $settings = TenantSettings::on('tenant')->first();
        $retention = $settings->audit_retention_months ?? 12;

        return view('admin.audit.stats', compact(
            'dailyVolume', 'topActions', 'topUsers', 'monthlyVolume', 'totalLogs', 'retention'
        ));
    }

    // ── Rétention & Purge ────────────────────────────────────────────────

    public function retention(Request $request)
    {
        $settings = TenantSettings::on('tenant')->first();
        $retention = $settings->audit_retention_months ?? 12;
        $cutoff = now()->subMonths($retention);

        // Prévisualisation : combien d'entrées seraient supprimées
        $toDelete = AuditLog::on('tenant')
            ->where('created_at', '<', $cutoff)
            ->count();

        // Répartition par action des entrées à supprimer
        $toDeleteByAction = DB::connection('tenant')
            ->table('audit_logs')
            ->where('created_at', '<', $cutoff)
            ->selectRaw('action, COUNT(*) as cnt')
            ->groupBy('action')
            ->orderByDesc('cnt')
            ->get();

        $totalLogs = AuditLog::on('tenant')->count();
        $oldestLog = AuditLog::on('tenant')->orderBy('created_at')->value('created_at');

        return view('admin.audit.retention', compact(
            'settings', 'retention', 'cutoff', 'toDelete', 'toDeleteByAction', 'totalLogs', 'oldestLog'
        ));
    }

    public function updateRetention(Request $request)
    {
        $validated = $request->validate([
            'audit_retention_months' => ['required', 'integer', 'in:3,6,12,24,36'],
        ]);

        TenantSettings::on('tenant')->first()?->update([
            'audit_retention_months' => $validated['audit_retention_months'],
        ]);

        return back()->with('success', "Rétention mise à jour à {$validated['audit_retention_months']} mois.");
    }

    public function purge(Request $request)
    {
        $request->validate([
            'confirm' => ['required', 'in:PURGER'],
        ]);

        $settings = TenantSettings::on('tenant')->first();
        $retention = $settings->audit_retention_months ?? 12;
        $cutoff = now()->subMonths($retention);

        $deleted = AuditLog::on('tenant')
            ->where('created_at', '<', $cutoff)
            ->delete();

        return back()->with('success', "{$deleted} entrée(s) supprimée(s) avant le {$cutoff->format('d/m/Y')}.");
    }

    // ── Export ───────────────────────────────────────────────────────────

    public function export(Request $request)
    {
        $format = $request->input('format', 'csv');
        $from = $request->input('from');
        $to = $request->input('to');
        $action = $request->input('action');

        $query = AuditLog::on('tenant')->orderBy('created_at');

        if ($from) {
            $query->where('created_at', '>=', $from.' 00:00:00');
        }
        if ($to) {
            $query->where('created_at', '<=', $to.' 23:59:59');
        }
        if ($action) {
            $query->where('action', $action);
        }

        $logs = $query->get();

        if ($format === 'json') {
            return response()->json($logs)
                ->header('Content-Disposition', 'attachment; filename="audit_logs_'.now()->format('Ymd_His').'.json"');
        }

        // CSV
        $filename = 'audit_logs_'.now()->format('Ymd_His').'.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($logs) {
            $handle = fopen('php://output', 'w');
            // BOM UTF-8 pour Excel
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['ID', 'Date', 'Utilisateur', 'Action', 'Détails', 'IP', 'User Agent'], ';');

            foreach ($logs as $log) {
                $details = $log->new_values ?? $log->old_values ?? '';
                fputcsv($handle, [
                    $log->id,
                    $log->created_at,
                    $log->user_name ?? '',
                    $log->action,
                    $details,
                    $log->ip_address ?? '',
                    $log->user_agent ?? '',
                ], ';');
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
