<?php

namespace App\Http\Controllers;

use App\Enums\ModuleKey;
use App\Enums\UserRole;
use App\Models\Platform\Organization;
use App\Models\Tenant\AuditLog;
use App\Models\Tenant\Department;
use App\Models\Tenant\MediaItem;
use App\Models\Tenant\Notification;
use App\Models\Tenant\Project;
use App\Models\Tenant\ProjectMember;
use App\Models\Tenant\Task;
use App\Models\Tenant\User;
use App\Services\TenantManager;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $org = app(TenantManager::class)->current();
        $role = UserRole::tryFrom($user->role ?? '');

        $isAdmin = $role?->atLeast(UserRole::ADMIN) ?? false;
        $isDgs = $role?->atLeast(UserRole::DGS) ?? false;
        $isAtLeastResp = $role?->atLeast(UserRole::RESP_SERVICE) ?? false;
        $isRespDirection = $role === UserRole::RESP_DIRECTION;
        $isRespService = $role === UserRole::RESP_SERVICE;
        $isSimpleUser = ! $isAtLeastResp;

        // ── Onboarding / avancement configuration (admin uniquement) ──
        $onboardingSteps = [];
        if ($isAdmin) {
            $ldapConfigured = false;
            try {
                $ldapConfigured = (bool) \App\Models\Tenant\TenantSettings::on('tenant')
                    ->whereNotNull('ldap_host')
                    ->value('ldap_host');
            } catch (\Throwable) {
            }

            $onboardingSteps = [
                ['label' => 'Authentification', 'done' => true],
                ['label' => '2FA',              'done' => (bool) $user->totp_enabled],
                ['label' => 'SMTP',             'done' => (bool) ($org?->smtp_host)],
                ['label' => 'LDAP',             'done' => $ldapConfigured],

                ['label' => 'Structure org.',   'done' => Department::on('tenant')->exists()],
            ];
        }

        // ── Scope hiérarchique ───────────────────────────────────────
        $visibleUserIds = $this->resolveVisibleUserIds($user, $role);

        // ── Stats utilisateurs (tenant entier — admin uniquement) ────
        $totalUsers = User::on('tenant')->count();
        $activeUsers = User::on('tenant')->where('status', 'active')->count();
        $ldapUsers = User::on('tenant')->whereNotNull('ldap_dn')->count();
        $adminUsers = User::on('tenant')->where('role', 'admin')->count();

        // Stats restreintes au périmètre visible
        $scopedUserCount = count($visibleUserIds);
        $scopedActiveCount = User::on('tenant')
            ->whereIn('id', $visibleUserIds)
            ->where('status', 'active')
            ->count();

        // ── Structure organisationnelle ──────────────────────────────
        $totalDirections = Department::on('tenant')->directions()->count();
        $totalServices = Department::on('tenant')->services()->count();

        // Départements gérés (resp. uniquement)
        $managedDeptIds = collect();
        if ($isRespDirection || $isRespService) {
            $managedDeptIds = $user->managedDepartments()->pluck('departments.id');
            if ($isRespDirection) {
                $childIds = Department::on('tenant')
                    ->where('type', 'service')
                    ->whereIn('parent_id', $managedDeptIds)
                    ->pluck('id');
                $managedDeptIds = $managedDeptIds->merge($childIds);
            }
        }

        // ── Stockage — scopé hiérarchiquement ───────────────────────
        $mediaQuery = MediaItem::on('tenant');
        if (! $isDgs) {
            $mediaQuery->whereIn('uploaded_by', $visibleUserIds);
        }

        $storageUsedBytes = (clone $mediaQuery)->sum('file_size_bytes');
        $storageUsedMb = round($storageUsedBytes / 1024 / 1024, 1);
        $storageQuotaMb = $org->storage_quota_mb ?? 10240;
        $storageUsedPct = $storageQuotaMb > 0
            ? min(100, round($storageUsedMb / $storageQuotaMb * 100))
            : 0;
        $mediaCount = (clone $mediaQuery)->count();

        $storageSizes = (clone $mediaQuery)
            ->selectRaw("
                SUM(CASE WHEN mime_type LIKE 'image/%' THEN file_size_bytes ELSE 0 END) as images,
                SUM(CASE WHEN mime_type LIKE 'video/%' THEN file_size_bytes ELSE 0 END) as videos,
                SUM(CASE WHEN mime_type = 'application/pdf' THEN file_size_bytes ELSE 0 END) as pdfs,
                COUNT(CASE WHEN mime_type LIKE 'image/%' THEN 1 END) as images_count,
                COUNT(CASE WHEN mime_type LIKE 'video/%' THEN 1 END) as videos_count,
                COUNT(CASE WHEN mime_type = 'application/pdf' THEN 1 END) as pdfs_count
            ")
            ->first();

        // ── Drawer stockage détaillé ─────────────────────────────────
        $storageByModule = $this->calcStorageByModule($visibleUserIds, $isDgs);
        $storageGrowthPerMonth = $this->calcStorageGrowth($visibleUserIds, $isDgs);
        $storageTopUsers = $this->calcTopUsers($visibleUserIds, $isDgs);

        // ── Stats inter-organisations (Super Admin uniquement) ───────
        $storagePerOrg = [];
        if (session('super_admin_logged_in')) {
            $storagePerOrg = $this->calcStoragePerOrg();
        }

        // ── Activité récente (scopée) ────────────────────────────────
        $recentLoginsQuery = User::on('tenant')->whereNotNull('last_login_at');
        $recentAuditQuery = AuditLog::on('tenant');

        if (! $isDgs) {
            $recentLoginsQuery->whereIn('id', $visibleUserIds);
            $recentAuditQuery->whereIn('user_id', $visibleUserIds);
        }

        $recentLogins = $recentLoginsQuery
            ->orderByDesc('last_login_at')
            ->limit(5)
            ->get(['id', 'name', 'role', 'last_login_at', 'last_login_ip']);

        $recentAudit = $recentAuditQuery
            ->orderByDesc('created_at')
            ->limit(8)
            ->get(['user_name', 'action', 'created_at', 'ip_address']);

        // ── Notifications ────────────────────────────────────────────
        $notifications = Notification::on('tenant')
            ->forUser($user->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $notifCount = Notification::on('tenant')
            ->forUser($user->id)
            ->unread()
            ->count();

        // ── Gestion de projet (module projects) ──────────────────────
        $myUrgentTasks = collect();
        $myProjectsCount = 0;
        $myActiveProjects = collect();

        if ($org?->hasModule(ModuleKey::PROJECTS)) {
            try {
                $myUrgentTasks = Task::on('tenant')
                    ->where('assigned_to', $user->id)
                    ->whereIn('status', ['todo', 'in_progress'])
                    ->whereIn('priority', ['urgent', 'high'])
                    ->orderByRaw("FIELD(priority,'urgent','high')")
                    ->orderBy('due_date')
                    ->with('project:id,name,color')
                    ->limit(5)
                    ->get();

                $myProjectsCount = ProjectMember::on('tenant')
                    ->where('user_id', $user->id)
                    ->count();

                // Projets actifs visibles pour le dashboard
                $myActiveProjects = Project::on('tenant')
                    ->visibleFor($user)
                    ->where('status', 'active')
                    ->withCount('tasks')
                    ->orderByDesc('updated_at')
                    ->limit(3)
                    ->get();
            } catch (\Throwable) {
            }
        }

        return view('dashboard', compact(
            'user', 'org', 'role', 'isAdmin', 'isDgs', 'onboardingSteps',
            'isAtLeastResp', 'isRespDirection', 'isRespService', 'isSimpleUser',
            'visibleUserIds',
            'totalUsers', 'activeUsers', 'ldapUsers', 'adminUsers',
            'scopedUserCount', 'scopedActiveCount',
            'totalDirections', 'totalServices', 'managedDeptIds',
            'storageUsedBytes', 'storageUsedMb', 'storageQuotaMb',
            'storageUsedPct', 'mediaCount', 'storageSizes',
            'storageByModule', 'storageGrowthPerMonth',
            'storageTopUsers', 'storagePerOrg',
            'recentLogins', 'recentAudit',
            'notifications', 'notifCount',
            'myUrgentTasks', 'myProjectsCount', 'myActiveProjects',
        ));
    }

    // ──────────────────────────────────────────────────────────────────
    // Résolution du périmètre visible
    // ──────────────────────────────────────────────────────────────────

    /**
     * Retourne la liste des IDs utilisateurs visibles par $user.
     *
     * @return array<int>
     */
    private function resolveVisibleUserIds(User $user, ?UserRole $role): array
    {
        if (! $role) {
            return [$user->id];
        }

        // Admin / Président / DGS → tout le tenant
        if ($role->atLeast(UserRole::DGS)) {
            return User::on('tenant')->pluck('id')->toArray();
        }

        // Resp. Direction → direction(s) + services enfants
        if ($role === UserRole::RESP_DIRECTION) {
            $directionIds = $user->managedDepartments()
                ->where('departments.type', 'direction')
                ->pluck('departments.id');

            $serviceIds = Department::on('tenant')
                ->where('type', 'service')
                ->whereIn('parent_id', $directionIds)
                ->pluck('id');

            $allDeptIds = $directionIds->merge($serviceIds);

            return User::on('tenant')
                ->whereHas('departments', fn ($q) => $q->whereIn('departments.id', $allDeptIds))
                ->pluck('id')
                ->toArray();
        }

        // Resp. Service → son/ses service(s)
        if ($role === UserRole::RESP_SERVICE) {
            $serviceIds = $user->managedDepartments()
                ->where('departments.type', 'service')
                ->pluck('departments.id');

            return User::on('tenant')
                ->whereHas('departments', fn ($q) => $q->whereIn('departments.id', $serviceIds))
                ->pluck('id')
                ->toArray();
        }

        // Utilisateur simple → lui-même
        return [$user->id];
    }

    // ──────────────────────────────────────────────────────────────────
    // Helpers calculs stockage
    // ──────────────────────────────────────────────────────────────────

    /**
     * Répartition du stockage par module, filtrée sur le périmètre.
     *
     * @param  array<int>  $visibleUserIds
     * @return array<string, int>
     */
    private function calcStorageByModule(array $visibleUserIds, bool $fullScope): array
    {
        $result = [
            'media' => 0,
            'media_count' => 0,
            'ged' => 0,
            'ged_count' => 0,
            'erp' => 0,
            'erp_tables' => 0,
            'erp_rows' => 0,
            'chat' => 0,
            'chat_files' => 0,
        ];

        // Photothèque (Phase 3)
        try {
            $q = DB::connection('tenant')->table('media_items')->whereNull('deleted_at');
            if (! $fullScope) {
                $q->whereIn('uploaded_by', $visibleUserIds);
            }
            $media = $q->selectRaw('SUM(file_size_bytes) as total, COUNT(*) as cnt')->first();
            $result['media'] = (int) ($media->total ?? 0);
            $result['media_count'] = (int) ($media->cnt ?? 0);
        } catch (\Throwable) {
        }

        // GED / Documents (Phase 5)
        try {
            $q = DB::connection('tenant')->table('documents')->whereNull('deleted_at');
            if (! $fullScope) {
                $q->whereIn('uploaded_by', $visibleUserIds);
            }
            $ged = $q->selectRaw('SUM(file_size_bytes) as total, COUNT(*) as cnt')->first();
            $result['ged'] = (int) ($ged->total ?? 0);
            $result['ged_count'] = (int) ($ged->cnt ?? 0);
        } catch (\Throwable) {
        }

        // ERP DataGrid (Phase 7) — pas de scope user pertinent
        try {
            $result['erp_tables'] = (int) DB::connection('tenant')->table('erp_table_configs')->count();
            $erpRows = 0;
            $configs = DB::connection('tenant')->table('erp_table_configs')->pluck('table_name');
            foreach ($configs as $tbl) {
                try {
                    $erpRows += DB::connection('tenant')->table($tbl)->count();
                } catch (\Throwable) {
                }
            }
            $result['erp_rows'] = $erpRows;
            $result['erp'] = $erpRows * 512;
        } catch (\Throwable) {
        }

        // Chat — pièces jointes (Phase 9)
        try {
            $q = DB::connection('tenant')
                ->table('chat_messages')
                ->whereNotNull('attachments')
                ->whereRaw('JSON_LENGTH(attachments) > 0');
            if (! $fullScope) {
                $q->whereIn('sender_id', $visibleUserIds);
            }
            $chatFiles = (int) $q->count();
            $result['chat_files'] = $chatFiles;
            $result['chat'] = $chatFiles * 200 * 1024;
        } catch (\Throwable) {
        }

        return $result;
    }

    /**
     * Croissance mensuelle en Go sur les 30 derniers jours.
     *
     * @param  array<int>  $visibleUserIds
     */
    private function calcStorageGrowth(array $visibleUserIds, bool $fullScope): float
    {
        try {
            $q = DB::connection('tenant')
                ->table('media_items')
                ->whereNull('deleted_at')
                ->where('created_at', '>=', now()->subDays(30));
            if (! $fullScope) {
                $q->whereIn('uploaded_by', $visibleUserIds);
            }

            return round($q->sum('file_size_bytes') / 1024 / 1024 / 1024, 2);
        } catch (\Throwable) {
            return 0.0;
        }
    }

    /**
     * Top 10 utilisateurs par volume uploadé.
     *
     * @param  array<int>  $visibleUserIds
     * @return array<int, array{name: string, bytes: int, count: int}>
     */
    private function calcTopUsers(array $visibleUserIds, bool $fullScope): array
    {
        try {
            $q = DB::connection('tenant')
                ->table('media_items as m')
                ->join('users as u', 'u.id', '=', 'm.uploaded_by')
                ->whereNull('m.deleted_at');
            if (! $fullScope) {
                $q->whereIn('m.uploaded_by', $visibleUserIds);
            }
            $rows = $q
                ->selectRaw('u.name, SUM(m.file_size_bytes) as total_bytes, COUNT(m.id) as cnt')
                ->groupBy('u.id', 'u.name')
                ->orderByDesc('total_bytes')
                ->limit(10)
                ->get();

            return $rows->map(fn ($r) => [
                'name' => $r->name,
                'bytes' => (int) $r->total_bytes,
                'count' => (int) $r->cnt,
            ])->toArray();
        } catch (\Throwable) {
            return [];
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // Stats inter-organisations — Super Admin
    // ──────────────────────────────────────────────────────────────────

    /**
     * Calcule le stockage total (tous modules confondus) de chaque organisation.
     * Itère sur toutes les bases tenant via TenantManager.
     *
     * Modules agrégés :
     *   - Photothèque  : media_items.file_size_bytes
     *   - GED          : documents.file_size_bytes          (Phase 5 — try/catch)
     *   - Chat         : chat_messages avec attachments      (Phase 9 — estimé)
     *
     * @return array<int, array{
     *     id: int, name: string, slug: string, plan: string,
     *     status: string, user_count: int,
     *     bytes_media: int, bytes_ged: int, bytes_chat: int,
     *     storage_bytes: int, storage_mb: float,
     *     quota_mb: int, quota_pct: int
     * }>
     */
    private function calcStoragePerOrg(): array
    {
        $orgs = Organization::orderBy('name')->get();
        $manager = app(TenantManager::class);
        $current = $manager->current();
        $results = [];

        foreach ($orgs as $org) {
            try {
                $manager->connectTo($org);

                $userCount = (int) DB::connection('tenant')->table('users')->count();

                // ── Photothèque ──────────────────────────────────────
                $bytesMedia = 0;
                try {
                    $bytesMedia = (int) DB::connection('tenant')
                        ->table('media_items')
                        ->whereNull('deleted_at')
                        ->sum('file_size_bytes');
                } catch (\Throwable) {
                }

                // ── GED / Documents (Phase 5) ────────────────────────
                $bytesGed = 0;
                try {
                    $bytesGed = (int) DB::connection('tenant')
                        ->table('documents')
                        ->whereNull('deleted_at')
                        ->sum('file_size_bytes');
                } catch (\Throwable) {
                }

                // ── Chat — pièces jointes (Phase 9, estimé 200 Ko/msg) ─
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

                $storageBytes = $bytesMedia + $bytesGed + $bytesChat;
                $storageMb = round($storageBytes / 1024 / 1024, 1);
                $quotaMb = $org->storage_quota_mb ?? 10240;
                $quotaPct = $quotaMb > 0
                    ? min(100, (int) round($storageMb / $quotaMb * 100))
                    : 0;

                $results[] = [
                    'id' => $org->id,
                    'name' => $org->name,
                    'slug' => $org->slug,
                    'plan' => $org->plan ?? 'communautaire',
                    'status' => $org->status,
                    'user_count' => $userCount,
                    'bytes_media' => $bytesMedia,
                    'bytes_ged' => $bytesGed,
                    'bytes_chat' => $bytesChat,
                    'storage_bytes' => $storageBytes,
                    'storage_mb' => $storageMb,
                    'quota_mb' => $quotaMb,
                    'quota_pct' => $quotaPct,
                ];
            } catch (\Throwable) {
                $results[] = [
                    'id' => $org->id,
                    'name' => $org->name,
                    'slug' => $org->slug,
                    'plan' => $org->plan ?? 'communautaire',
                    'status' => $org->status,
                    'user_count' => 0,
                    'bytes_media' => 0,
                    'bytes_ged' => 0,
                    'bytes_chat' => 0,
                    'storage_bytes' => 0,
                    'storage_mb' => 0.0,
                    'quota_mb' => $org->storage_quota_mb ?? 10240,
                    'quota_pct' => 0,
                ];
            }
        }

        // Restaurer la connexion au tenant courant
        if ($current) {
            try {
                $manager->connectTo($current);
            } catch (\Throwable) {
            }
        }

        return $results;
    }
}
