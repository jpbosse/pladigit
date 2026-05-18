<?php

namespace App\Console\Commands;

use App\Models\Platform\Organization;
use App\Models\Platform\PlatformSettings;
use App\Models\Tenant\TenantSettings;
use App\Services\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Purge les journaux d'audit selon la rétention RGPD configurée par tenant.
 *
 * Tables purgées :
 *   - audit_logs          (actions utilisateur générales)
 *   - datagrid_audit_logs (actions sur les grilles DataGrid)
 *
 * La durée de rétention est lue depuis `tenant_settings.audit_retention_months`
 * (valeur par défaut : 12 mois).
 *
 * Remarque : AuditLog::delete() est intentionnellement bloqué au niveau instance.
 * On passe donc par DB::connection('tenant')->table() pour la suppression en masse.
 *
 * Usage :
 *   php artisan pladigit:purge-audit-logs               — tous les tenants
 *   php artisan pladigit:purge-audit-logs --slug=demo   — un tenant précis
 *   php artisan pladigit:purge-audit-logs --dry-run     — aperçu sans suppression
 */
class PurgeAuditLogsCommand extends Command
{
    protected $signature = 'pladigit:purge-audit-logs
                            {--slug= : Slug d\'une organisation spécifique}
                            {--dry-run : Afficher ce qui serait supprimé sans agir}';

    protected $description = 'Purge RGPD des journaux d\'audit par tenant selon audit_retention_months';

    public function __construct(private TenantManager $tenantManager)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dry = $this->option('dry-run');
        $slug = $this->option('slug');

        if ($dry) {
            $this->warn('[dry-run] Aucune donnée ne sera supprimée.');
            $this->newLine();
        }

        $query = Organization::query()->whereIn('status', ['active', 'trial']);

        if ($slug) {
            $query->where('slug', $slug);
        }

        $organizations = $query->get();

        if ($organizations->isEmpty()) {
            $this->warn('Aucune organisation trouvée.');

            return self::SUCCESS;
        }

        $this->info("Purge audit_logs — {$organizations->count()} organisation(s).");
        $this->newLine();

        // ── Plafond absolu plateforme ─────────────────────────────────
        $platformSettings = PlatformSettings::first();
        $maxMonths = max(1, (int) ($platformSettings !== null ? $platformSettings->audit_max_retention_months : 60));
        $this->line("  Plafond plateforme : {$maxMonths} mois max.");
        $this->newLine();

        $totalAudit = 0;
        $totalDatagrid = 0;
        $errors = 0;

        foreach ($organizations as $org) {
            $this->line("  → <fg=cyan>{$org->slug}</> (<fg=gray>{$org->db_name}</>)");

            try {
                $this->tenantManager->connectTo($org);

                $settings = TenantSettings::first();
                $months = max(1, (int) ($settings !== null ? $settings->audit_retention_months : 12));
                // Appliquer le plafond plateforme — prend le minimum des deux
                $months = min($months, $maxMonths);
                $cutoff = now()->subMonths($months);

                $this->line("     Rétention : {$months} mois (plafond : {$maxMonths}) — cutoff : {$cutoff->format('d/m/Y')}");

                // ── audit_logs ────────────────────────────────────────
                $auditCount = DB::connection('tenant')
                    ->table('audit_logs')
                    ->where('created_at', '<', $cutoff)
                    ->count();

                $this->line("     audit_logs à supprimer : {$auditCount}");

                if (! $dry && $auditCount > 0) {
                    DB::connection('tenant')
                        ->table('audit_logs')
                        ->where('created_at', '<', $cutoff)
                        ->delete();
                }

                // ── datagrid_audit_logs ───────────────────────────────
                $datagridCount = 0;
                if (DB::connection('tenant')->getSchemaBuilder()->hasTable('datagrid_audit_logs')) {
                    $datagridCount = DB::connection('tenant')
                        ->table('datagrid_audit_logs')
                        ->where('created_at', '<', $cutoff)
                        ->count();

                    $this->line("     datagrid_audit_logs à supprimer : {$datagridCount}");

                    if (! $dry && $datagridCount > 0) {
                        DB::connection('tenant')
                            ->table('datagrid_audit_logs')
                            ->where('created_at', '<', $cutoff)
                            ->delete();
                    }
                }

                $totalAudit += $auditCount;
                $totalDatagrid += $datagridCount;

                $this->line('     <fg=green>✓ OK</>');

            } catch (\Throwable $e) {
                $this->line("     <fg=red>✗ Erreur : {$e->getMessage()}</>");
                Log::error("pladigit:purge-audit-logs — {$org->slug} : {$e->getMessage()}");
                $errors++;
            } finally {
                DB::purge('tenant');
            }

            $this->newLine();
        }

        // ── Résumé ────────────────────────────────────────────────────
        $this->line('─────────────────────────────────────────');
        if ($dry) {
            $this->line("  [dry-run] audit_logs : {$totalAudit} — datagrid_audit_logs : {$totalDatagrid}");
        } else {
            $this->line("  <fg=green>Supprimés</> — audit_logs : {$totalAudit} — datagrid_audit_logs : {$totalDatagrid}");
        }
        if ($errors > 0) {
            $this->line("  <fg=red>✗ {$errors} erreur(s)</>");
        }

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
