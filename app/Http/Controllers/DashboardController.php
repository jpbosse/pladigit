<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Tenant\AuditLog;
use App\Models\Tenant\Department;
use App\Models\Tenant\MediaItem;
use App\Models\Tenant\Notification;
use App\Models\Tenant\User;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $org = app(\App\Services\TenantManager::class)->current();
        $role = UserRole::tryFrom($user->role ?? '');

        $isAdmin = $role?->atLeast(UserRole::ADMIN) ?? false;
        $isDgs = $role?->atLeast(UserRole::DGS) ?? false;

        // ── Stats utilisateurs ──────────────────────────────
        $totalUsers = User::on('tenant')->count();
        $activeUsers = User::on('tenant')->where('status', 'active')->count();
        $ldapUsers = User::on('tenant')->whereNotNull('ldap_dn')->count();
        $adminUsers = User::on('tenant')->where('role', 'admin')->count();

        // ── Structure organisationnelle ─────────────────────
        $totalDirections = Department::on('tenant')->directions()->count();
        $totalServices = Department::on('tenant')->services()->count();

        // ── Stockage photothèque ────────────────────────────
        // Somme réelle depuis media_items (fichiers non supprimés)
        $storageUsedBytes = MediaItem::on('tenant')->sum('file_size_bytes');
        $storageUsedMb = round($storageUsedBytes / 1024 / 1024, 1);
        $storageQuotaMb = $org->storage_quota_mb ?? 5120; // défaut 5 Go
        $storageUsedPct = $storageQuotaMb > 0
            ? min(100, round($storageUsedMb / $storageQuotaMb * 100))
            : 0;
        $mediaCount = MediaItem::on('tenant')->count();

        // Répartition par type pour le drawer stockage
        $storageSizes = MediaItem::on('tenant')
            ->selectRaw("
                SUM(CASE WHEN mime_type LIKE 'image/%' THEN file_size_bytes ELSE 0 END) as images,
                SUM(CASE WHEN mime_type LIKE 'video/%' THEN file_size_bytes ELSE 0 END) as videos,
                SUM(CASE WHEN mime_type = 'application/pdf' THEN file_size_bytes ELSE 0 END) as pdfs,
                COUNT(CASE WHEN mime_type LIKE 'image/%' THEN 1 END) as images_count,
                COUNT(CASE WHEN mime_type LIKE 'video/%' THEN 1 END) as videos_count,
                COUNT(CASE WHEN mime_type = 'application/pdf' THEN 1 END) as pdfs_count
            ")
            ->first();

        // ── Activité récente ────────────────────────────────
        $recentLogins = User::on('tenant')
            ->whereNotNull('last_login_at')
            ->orderByDesc('last_login_at')
            ->limit(5)
            ->get(['id', 'name', 'role', 'last_login_at', 'last_login_ip']);

        $recentAudit = AuditLog::on('tenant')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get(['user_name', 'action', 'created_at', 'ip_address']);

        // ── Notifications de l'utilisateur ─────────────────
        $notifications = Notification::on('tenant')
            ->forUser($user->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $notifCount = Notification::on('tenant')
            ->forUser($user->id)
            ->unread()
            ->count();

        return view('dashboard', compact(
            'user', 'org', 'role', 'isAdmin', 'isDgs',
            // Utilisateurs
            'totalUsers', 'activeUsers', 'ldapUsers', 'adminUsers',
            // Structure
            'totalDirections', 'totalServices',
            // Stockage
            'storageUsedBytes', 'storageUsedMb', 'storageQuotaMb',
            'storageUsedPct', 'mediaCount', 'storageSizes',
            // Activité
            'recentLogins', 'recentAudit',
            // Notifications
            'notifications', 'notifCount',
        ));
    }
}
