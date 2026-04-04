<?php

namespace App\Http\Controllers\Admin;

use App\Console\Commands\PurgeGedCommand;
use App\Http\Controllers\Controller;
use App\Models\Tenant\TenantSettings;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminPurgeController extends Controller
{
    public function __construct(
        private readonly AuditService $audit,
    ) {}

    /**
     * Affiche la page de configuration et d'exécution de la purge GED.
     */
    public function index(): View
    {
        $settings = TenantSettings::firstOrNew([]);

        return view('admin.purge.index', compact('settings'));
    }

    /**
     * Met à jour les paramètres de rétention GED.
     */
    public function updateConfig(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ged_deleted_retention_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'ged_versions_max_count' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $settings = TenantSettings::firstOrNew([]);
        $settings->fill([
            'ged_deleted_retention_days' => $data['ged_deleted_retention_days'] ?? null,
            'ged_versions_max_count' => $data['ged_versions_max_count'] ?? null,
            'updated_at' => now(),
        ]);
        $settings->save();

        $this->audit->log('admin.purge.config.updated', auth()->user(), [
            'new' => [
                'ged_deleted_retention_days' => $settings->ged_deleted_retention_days,
                'ged_versions_max_count' => $settings->ged_versions_max_count,
            ],
        ]);

        return redirect()->route('admin.purge.index')->with('success', 'Configuration de purge enregistrée.');
    }

    /**
     * Retourne un aperçu (dry-run) de ce qui serait purgé — JSON.
     */
    public function preview(): JsonResponse
    {
        /** @var PurgeGedCommand $command */
        $command = app(PurgeGedCommand::class);
        $stats = $command->stats();

        return response()->json([
            'ok' => true,
            'stats' => $stats,
        ]);
    }

    /**
     * Exécute la purge GED manuellement — JSON.
     * Les stats sont calculées avant la purge pour restituer ce qui a été supprimé.
     */
    public function run(): JsonResponse
    {
        /** @var PurgeGedCommand $command */
        $command = app(PurgeGedCommand::class);

        // Capturer les stats avant la purge (dry-run)
        $stats = $command->stats();

        // Exécuter la purge réelle
        \Artisan::call('ged:purge');

        $this->audit->log('admin.purge.run', auth()->user(), [
            'new' => $stats,
        ]);

        return response()->json([
            'ok' => true,
            'stats' => $stats,
        ]);
    }
}
