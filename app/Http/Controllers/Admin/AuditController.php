<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\AuditLog;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $query = AuditLog::on('tenant')
            ->orderByDesc('created_at');

        // Filtres
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

        $logs = $query->paginate(50)->withQueryString();
        $actions = AuditLog::on('tenant')
            ->selectRaw('action, count(*) as cnt')
            ->groupBy('action')
            ->orderByDesc('cnt')
            ->pluck('cnt', 'action');

        return view('admin.audit.index', compact('logs', 'actions'));
    }
}
