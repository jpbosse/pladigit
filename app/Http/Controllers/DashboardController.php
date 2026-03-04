<?php

namespace App\Http\Controllers;

use App\Models\Tenant\AuditLog;
use App\Models\Tenant\Department;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $org = app(\App\Services\TenantManager::class)->current();

        // ── Stats utilisateurs ────────────────────────────────────────
        $totalUsers = User::on('tenant')->count();
        $activeUsers = User::on('tenant')->where('status', 'active')->count();
        $ldapUsers = User::on('tenant')->whereNotNull('ldap_dn')->count();
        $adminUsers = User::on('tenant')->where('role', 'admin')->count();

        $usersByRole = User::on('tenant')
            ->select('role', DB::raw('count(*) as total'))
            ->groupBy('role')
            ->pluck('total', 'role');

        // ── Stats départements ────────────────────────────────────────
        $totalDirections = Department::on('tenant')->directions()->count();
        $totalServices = Department::on('tenant')->services()->count();

        // ── Activité récente ──────────────────────────────────────────
        $recentLogins = User::on('tenant')
            ->whereNotNull('last_login_at')
            ->orderByDesc('last_login_at')
            ->limit(5)
            ->get(['id', 'name', 'role', 'last_login_at', 'last_login_ip']);

        $recentAudit = AuditLog::on('tenant')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get(['user_name', 'action', 'created_at', 'ip_address']);

        return view('dashboard', compact(
            'user', 'org',
            'totalUsers', 'activeUsers', 'ldapUsers', 'adminUsers', 'usersByRole',
            'totalDirections', 'totalServices',
            'recentLogins', 'recentAudit'
        ));
    }
}
