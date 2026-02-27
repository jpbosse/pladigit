<?php

namespace App\Http\Controllers;

use App\Models\Tenant\User;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $org = app(\App\Services\TenantManager::class)->current();

        // Stats utilisateurs
        $totalUsers = User::count();
        $activeUsers = User::where('status', 'active')->count();
        $ldapUsers = User::whereNotNull('ldap_dn')->count();
        $adminUsers = User::where('role', 'admin')->count();

        // Stats par rôle
        $usersByRole = User::select('role', DB::raw('count(*) as total'))
            ->groupBy('role')
            ->pluck('total', 'role');

        return view('dashboard', compact(
            'user', 'org',
            'totalUsers', 'activeUsers', 'ldapUsers', 'adminUsers',
            'usersByRole'
        ));
    }
}
