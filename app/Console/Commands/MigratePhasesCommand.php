<?php

namespace App\Console\Commands;

use App\Models\Platform\Organization;
use App\Models\Tenant\Project;
use App\Models\Tenant\ProjectMilestone;
use App\Services\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Migre les jalons existants vers la nouvelle structure phases/jalons.
 *
 * Ce que fait la commande :
 *   1. Analyse tous les jalons du tenant (parent_id null, sort_order = 0)
 *   2. Les renumérote par sort_order (10, 20, 30...) dans l'ordre de leur due_date
 *   3. Affiche un rapport détaillé de l'état avant/après
 *   4. Les jalons existants deviennent des "phases autonomes" (sans enfants)
 *      — ils sont déjà compatibles avec la nouvelle structure
 *   5. Aucune donnée n'est supprimée — les tâches restent attachées à leurs jalons
 *
 * Usage :
 *   php artisan pladigit:migrate-phases                  — tous les tenants actifs
 *   php artisan pladigit:migrate-phases --tenant=demo    — un seul tenant
 *   php artisan pladigit:migrate-phases --dry-run        — aperçu sans modifier
 */
class MigratePhasesCommand extends Command
{
    protected $signature = 'pladigit:migrate-phases
                            {--tenant= : Slug du tenant à migrer}
                            {--dry-run : Afficher le plan sans modifier la base}';

    protected $description = 'Migre les jalons existants vers la structure phases/jalons (renumérote sort_order)';

    public function __construct(private TenantManager $tenantManager)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $slug   = $this->option('tenant');

        if ($dryRun) {
            $this->warn('⚠  Mode dry-run — aucune modification ne sera appliquée.');
            $this->newLine();
        }

        // Résoudre les organisations à traiter
        $query = Organization::where('status', 'active');
        if ($slug) {
            $query->where('slug', $slug);
            if ($query->doesntExist()) {
                $this->error("Tenant « {$slug} » introuvable ou inactif.");
                return Command::FAILURE;
            }
        }

        $orgs = $query->get();
        $this->info("Organisations à traiter : {$orgs->count()}");
        $this->newLine();

        $totalMilestones = 0;
        $totalProjects   = 0;

        foreach ($orgs as $org) {
            $this->tenantManager->connectTo($org);

            $this->line("── <fg=cyan>{$org->name}</> ({$org->slug})");

            $projects = Project::on('tenant')->withCount('milestones')->get();

            foreach ($projects as $project) {
                if ($project->milestones_count === 0) {
                    continue;
                }

                $milestones = ProjectMilestone::on('tenant')
                    ->where('project_id', $project->id)
                    ->whereNull('parent_id')
                    ->orderBy('due_date')
                    ->orderBy('id')
                    ->get();

                if ($milestones->isEmpty()) {
                    continue;
                }

                $totalProjects++;
                $this->line("   📁 {$project->name} — {$milestones->count()} jalons/phases");

                foreach ($milestones as $i => $ms) {
                    $newOrder   = ($i + 1) * 10;
                    $taskCount  = DB::connection('tenant')
                        ->table('tasks')
                        ->where('milestone_id', $ms->id)
                        ->whereNull('deleted_at')
                        ->count();

                    $childCount = ProjectMilestone::on('tenant')
                        ->where('parent_id', $ms->id)
                        ->count();

                    $type = $childCount > 0 ? '📦 Phase' : '🏁 Jalon';
                    $icon = $ms->reached_at ? '✓' : ($ms->due_date && $ms->due_date->isPast() ? '!' : '·');

                    $this->line(sprintf(
                        '      %s  [sort: %3d → %3d]  %s %s  (%d tâche%s%s)',
                        $icon,
                        $ms->sort_order,
                        $newOrder,
                        $type,
                        $ms->title,
                        $taskCount,
                        $taskCount > 1 ? 's' : '',
                        $childCount > 0 ? ", {$childCount} jalons enfants" : ''
                    ));

                    if (! $dryRun) {
                        $ms->update(['sort_order' => $newOrder]);
                    }

                    $totalMilestones++;
                }

                $this->newLine();
            }
        }

        $this->newLine();

        if ($dryRun) {
            $this->warn("Dry-run terminé — {$totalMilestones} jalons analysés sur {$totalProjects} projet(s).");
            $this->line('Relancez sans --dry-run pour appliquer.');
        } else {
            $this->info("✓ Migration terminée — {$totalMilestones} jalons numérotés sur {$totalProjects} projet(s).");
            $this->newLine();
            $this->line('Prochaines étapes :');
            $this->line('  1. Ouvrez un projet dans Pladigit → onglet "But & description"');
            $this->line('  2. Vos jalons existants sont maintenant des phases autonomes');
            $this->line('  3. Utilisez "+ Jalon" pour créer des jalons enfants sous chaque phase');
            $this->line('  4. Utilisez ↑↓ pour réordonner les phases');
        }

        return Command::SUCCESS;
    }
}
