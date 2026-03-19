<?php

namespace App\Console\Commands;

use App\Models\Platform\Organization;
use App\Models\Tenant\Project;
use App\Models\Tenant\ProjectMilestone;
use App\Models\Tenant\Task;
use App\Models\Tenant\User;
use App\Services\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Copie tous les projets d'un tenant source vers un tenant destination.
 *
 * Ce qui est copié :
 *   ✓ Projets (name, description, status, dates, color)
 *   ✓ Phases & jalons (avec hiérarchie parent_id preservée)
 *   ✓ Tâches (avec sous-tâches parent_task_id preservé, sans récurrence)
 *   ✓ Membres du projet (rôles preservés)
 *   ✓ Budgets
 *   ✓ Risques
 *   ✓ Parties prenantes
 *   ✓ Plan de communication
 *   ✓ Observations
 *
 * Ce qui est remappé :
 *   created_by, assigned_to, user_id → premier admin du tenant destination
 *   (les utilisateurs n'existent pas dans la base destination)
 *
 * Usage :
 *   php artisan pladigit:copy-projects --from=cedbos --to=demo
 *   php artisan pladigit:copy-projects --from=cedbos --to=demo --dry-run
 *   php artisan pladigit:copy-projects --from=cedbos --to=demo --project=1
 */
class CopyProjectsCommand extends Command
{
    protected $signature = 'pladigit:copy-projects
                            {--from= : Slug du tenant source (obligatoire)}
                            {--to=   : Slug du tenant destination (obligatoire)}
                            {--project= : ID du projet source à copier (optionnel — tous si absent)}
                            {--dry-run  : Afficher le plan sans modifier la base}
                            {--wipe     : Supprimer les projets existants dans le tenant destination avant la copie}';

    protected $description = 'Copie les projets d\'un tenant vers un autre (structure + données)';

    public function __construct(private TenantManager $tenantManager)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $fromSlug = $this->option('from');
        $toSlug = $this->option('to');
        $dryRun = $this->option('dry-run');
        $wipe = $this->option('wipe');
        $projectId = $this->option('project');

        if (! $fromSlug || ! $toSlug) {
            $this->error('--from et --to sont obligatoires.');

            return Command::FAILURE;
        }

        if ($fromSlug === $toSlug) {
            $this->error('Source et destination identiques.');

            return Command::FAILURE;
        }

        // Résoudre les organisations
        $srcOrg = Organization::where('slug', $fromSlug)->first();
        $dstOrg = Organization::where('slug', $toSlug)->first();

        if (! $srcOrg) {
            $this->error("Organisation source « {$fromSlug} » introuvable.");

            return Command::FAILURE;
        }
        if (! $dstOrg) {
            $this->error("Organisation destination « {$toSlug} » introuvable.");

            return Command::FAILURE;
        }

        if ($dryRun) {
            $this->warn('⚠  Mode dry-run — aucune modification.');
            $this->newLine();
        }

        // ── Charger les projets source ────────────────────────────────────
        $this->tenantManager->connectTo($srcOrg);

        $query = Project::on('tenant')->with([
            'milestones',
            'milestones.children',
            'projectMembers',
            'budgets',
            'risks',
            'stakeholders',
            'commActions',
            'observations',
        ]);

        if ($projectId) {
            $query->where('id', $projectId);
        }

        $projects = $query->get();

        if ($projects->isEmpty()) {
            $this->warn('Aucun projet trouvé dans le tenant source.');

            return Command::SUCCESS;
        }

        // Charger toutes les tâches avec leurs relations
        $allSrcTasks = collect();
        foreach ($projects as $project) {
            $tasks = Task::on('tenant')
                ->where('project_id', $project->id)
                ->whereNull('deleted_at')
                ->orderBy('parent_task_id') // parents avant enfants
                ->orderBy('sort_order')
                ->get();
            $allSrcTasks = $allSrcTasks->merge($tasks);
        }

        $this->info("Source : {$srcOrg->name} — {$projects->count()} projet(s), {$allSrcTasks->count()} tâche(s)");
        $this->info("Destination : {$dstOrg->name}");
        $this->newLine();

        if ($dryRun) {
            foreach ($projects as $p) {
                $msCount = $p->milestones->count() + $p->milestones->flatMap->children->count();
                $tCount = $allSrcTasks->where('project_id', $p->id)->count();
                $this->line("  📁 {$p->name} — {$msCount} jalons/phases, {$tCount} tâches");
            }
            $this->newLine();
            $this->warn('Dry-run terminé. Relancez sans --dry-run pour copier.');

            return Command::SUCCESS;
        }

        // ── Connexion destination ─────────────────────────────────────────
        $this->tenantManager->connectTo($dstOrg);

        // Trouver le premier admin dans la destination pour les remappages
        $dstAdmin = User::on('tenant')
            ->whereIn('role', ['admin', 'president', 'dgs'])
            ->where('status', 'active')
            ->orderBy('id')
            ->first();

        if (! $dstAdmin) {
            $this->error("Aucun admin trouvé dans le tenant destination « {$toSlug} ».");

            return Command::FAILURE;
        }

        $this->line("Remappage utilisateurs → <fg=cyan>{$dstAdmin->name}</> (ID {$dstAdmin->id})");
        $this->newLine();

        // Optionnel : vider les projets existants
        if ($wipe) {
            $existing = Project::on('tenant')->count();
            if ($existing > 0) {
                $this->warn("--wipe : suppression de {$existing} projet(s) existant(s) dans {$toSlug}...");
                DB::connection('tenant')->statement('SET FOREIGN_KEY_CHECKS=0');
                DB::connection('tenant')->table('task_dependencies')->truncate();
                DB::connection('tenant')->table('task_comments')->truncate();
                DB::connection('tenant')->table('tasks')->truncate();
                DB::connection('tenant')->table('project_milestones')->truncate();
                DB::connection('tenant')->table('project_members')->truncate();
                DB::connection('tenant')->table('project_budgets')->truncate();
                DB::connection('tenant')->table('project_risks')->truncate();
                DB::connection('tenant')->table('project_stakeholders')->truncate();
                DB::connection('tenant')->table('project_comm_actions')->truncate();
                DB::connection('tenant')->table('project_observations')->truncate();
                DB::connection('tenant')->table('projects')->truncate();
                DB::connection('tenant')->statement('SET FOREIGN_KEY_CHECKS=1');
                $this->line('  ✓ Tables vidées.');
                $this->newLine();
            }
        }

        // ── Copie projet par projet ───────────────────────────────────────
        foreach ($projects as $srcProject) {
            $this->line("📁 Copie : <fg=cyan>{$srcProject->name}</>");

            // 1. Créer le projet
            $dstProject = Project::on('tenant')->create([
                'created_by' => $dstAdmin->id,
                'name' => $srcProject->name,
                'description' => $srcProject->description,
                'status' => $srcProject->status,
                'start_date' => $srcProject->start_date,
                'due_date' => $srcProject->due_date,
                'color' => $srcProject->color,
            ]);

            // 2. Copier phases/jalons (deux passes pour respecter parent_id)
            // Passe 1 : phases (parent_id null)
            $milestoneMap = []; // ancien_id => nouveau_id

            // Recharger depuis source pour cette copie
            $this->tenantManager->connectTo($srcOrg);
            $srcMilestones = ProjectMilestone::on('tenant')
                ->where('project_id', $srcProject->id)
                ->whereNull('parent_id')
                ->orderBy('sort_order')
                ->orderBy('due_date')
                ->get();

            $srcChildren = ProjectMilestone::on('tenant')
                ->where('project_id', $srcProject->id)
                ->whereNotNull('parent_id')
                ->orderBy('sort_order')
                ->orderBy('due_date')
                ->get();

            $this->tenantManager->connectTo($dstOrg);

            foreach ($srcMilestones as $srcMs) {
                $dstMs = ProjectMilestone::on('tenant')->create([
                    'project_id' => $dstProject->id,
                    'parent_id' => null,
                    'title' => $srcMs->title,
                    'description' => $srcMs->description,
                    'start_date' => $srcMs->start_date,
                    'due_date' => $srcMs->due_date,
                    'reached_at' => $srcMs->reached_at,
                    'color' => $srcMs->color,
                    'sort_order' => $srcMs->sort_order,
                ]);
                $milestoneMap[$srcMs->id] = $dstMs->id;
            }

            // Passe 2 : jalons enfants
            foreach ($srcChildren as $srcChild) {
                $newParentId = $milestoneMap[$srcChild->parent_id] ?? null;
                $dstChild = ProjectMilestone::on('tenant')->create([
                    'project_id' => $dstProject->id,
                    'parent_id' => $newParentId,
                    'title' => $srcChild->title,
                    'description' => $srcChild->description,
                    'start_date' => $srcChild->start_date,
                    'due_date' => $srcChild->due_date,
                    'reached_at' => $srcChild->reached_at,
                    'color' => $srcChild->color,
                    'sort_order' => $srcChild->sort_order,
                ]);
                $milestoneMap[$srcChild->id] = $dstChild->id;
            }

            $msTotal = count($milestoneMap);
            $this->line("   ✓ {$msTotal} jalons/phases copiés");

            // 3. Copier les tâches (deux passes pour parent_task_id)
            $this->tenantManager->connectTo($srcOrg);
            $srcTasks = Task::on('tenant')
                ->where('project_id', $srcProject->id)
                ->whereNull('deleted_at')
                ->orderByRaw('ISNULL(parent_task_id), parent_task_id, sort_order')
                ->get();
            $this->tenantManager->connectTo($dstOrg);

            $taskMap = []; // ancien_id => nouveau_id

            // Passe 1 : tâches racines
            foreach ($srcTasks->whereNull('parent_task_id') as $srcTask) {
                $dstTask = Task::on('tenant')->create([
                    'project_id' => $dstProject->id,
                    'created_by' => $dstAdmin->id,
                    'assigned_to' => $srcTask->assigned_to ? $dstAdmin->id : null,
                    'parent_task_id' => null,
                    'milestone_id' => $srcTask->milestone_id ? ($milestoneMap[$srcTask->milestone_id] ?? null) : null,
                    'title' => $srcTask->title,
                    'description' => $srcTask->description,
                    'status' => $srcTask->status,
                    'priority' => $srcTask->priority,
                    'start_date' => $srcTask->start_date,
                    'due_date' => $srcTask->due_date,
                    'estimated_hours' => $srcTask->estimated_hours,
                    'actual_hours' => $srcTask->actual_hours,
                    'sort_order' => $srcTask->sort_order,
                ]);
                $taskMap[$srcTask->id] = $dstTask->id;
            }

            // Passe 2 : sous-tâches
            foreach ($srcTasks->whereNotNull('parent_task_id') as $srcTask) {
                $newParentTaskId = $taskMap[$srcTask->parent_task_id] ?? null;
                $dstTask = Task::on('tenant')->create([
                    'project_id' => $dstProject->id,
                    'created_by' => $dstAdmin->id,
                    'assigned_to' => $srcTask->assigned_to ? $dstAdmin->id : null,
                    'parent_task_id' => $newParentTaskId,
                    'milestone_id' => $srcTask->milestone_id ? ($milestoneMap[$srcTask->milestone_id] ?? null) : null,
                    'title' => $srcTask->title,
                    'description' => $srcTask->description,
                    'status' => $srcTask->status,
                    'priority' => $srcTask->priority,
                    'start_date' => $srcTask->start_date,
                    'due_date' => $srcTask->due_date,
                    'estimated_hours' => $srcTask->estimated_hours,
                    'actual_hours' => $srcTask->actual_hours,
                    'sort_order' => $srcTask->sort_order,
                ]);
                $taskMap[$srcTask->id] = $dstTask->id;
            }

            $this->line('   ✓ '.count($taskMap).' tâches copiées');

            // 4. Membre owner
            DB::connection('tenant')->table('project_members')->insert([
                'project_id' => $dstProject->id,
                'user_id' => $dstAdmin->id,
                'role' => 'owner',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 5. Budgets
            $this->tenantManager->connectTo($srcOrg);
            $budgets = DB::connection('tenant')
                ->table('project_budgets')
                ->where('project_id', $srcProject->id)
                ->whereNull('deleted_at')
                ->get();
            $this->tenantManager->connectTo($dstOrg);

            foreach ($budgets as $b) {
                DB::connection('tenant')->table('project_budgets')->insert([
                    'project_id' => $dstProject->id,
                    'type' => $b->type,
                    'label' => $b->label,
                    'year' => $b->year,
                    'amount_planned' => $b->amount_planned,
                    'amount_committed' => $b->amount_committed,
                    'amount_paid' => $b->amount_paid,
                    'cofinancer' => $b->cofinancer,
                    'cofinancing_rate' => $b->cofinancing_rate,
                    'notes' => $b->notes,
                    'created_by' => $dstAdmin->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            if ($budgets->count()) {
                $this->line("   ✓ {$budgets->count()} ligne(s) budgétaire(s)");
            }

            // 6. Risques
            $this->tenantManager->connectTo($srcOrg);
            $risks = DB::connection('tenant')
                ->table('project_risks')
                ->where('project_id', $srcProject->id)
                ->whereNull('deleted_at')
                ->get();
            $this->tenantManager->connectTo($dstOrg);

            foreach ($risks as $r) {
                DB::connection('tenant')->table('project_risks')->insert([
                    'project_id' => $dstProject->id,
                    'title' => $r->title,
                    'description' => $r->description,
                    'category' => $r->category,
                    'probability' => $r->probability,
                    'impact' => $r->impact,
                    'status' => $r->status,
                    'mitigation_plan' => $r->mitigation_plan,
                    'owner_id' => $r->owner_id ? $dstAdmin->id : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            if ($risks->count()) {
                $this->line("   ✓ {$risks->count()} risque(s)");
            }

            // 7. Parties prenantes
            $this->tenantManager->connectTo($srcOrg);
            $stakeholders = DB::connection('tenant')
                ->table('project_stakeholders')
                ->where('project_id', $srcProject->id)
                ->whereNull('deleted_at')
                ->get();
            $this->tenantManager->connectTo($dstOrg);

            foreach ($stakeholders as $s) {
                DB::connection('tenant')->table('project_stakeholders')->insert([
                    'project_id' => $dstProject->id,
                    'user_id' => null, // utilisateur non mappable
                    'name' => $s->name ?? $s->role,
                    'role' => $s->role,
                    'adhesion' => $s->adhesion,
                    'influence' => $s->influence,
                    'notes' => $s->notes,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            if ($stakeholders->count()) {
                $this->line("   ✓ {$stakeholders->count()} partie(s) prenante(s)");
            }

            // 8. Plan de communication
            $this->tenantManager->connectTo($srcOrg);
            $commActions = DB::connection('tenant')
                ->table('project_comm_actions')
                ->where('project_id', $srcProject->id)
                ->whereNull('deleted_at')
                ->get();
            $this->tenantManager->connectTo($dstOrg);

            foreach ($commActions as $ca) {
                DB::connection('tenant')->table('project_comm_actions')->insert([
                    'project_id' => $dstProject->id,
                    'title' => $ca->title,
                    'target_audience' => $ca->target_audience,
                    'channel' => $ca->channel,
                    'message' => $ca->message,
                    'resources_needed' => $ca->resources_needed,
                    'planned_at' => $ca->planned_at,
                    'done_at' => $ca->done_at,
                    'responsible_id' => $ca->responsible_id ? $dstAdmin->id : null,
                    'notes' => $ca->notes,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            if ($commActions->count()) {
                $this->line("   ✓ {$commActions->count()} action(s) de communication");
            }

            $this->newLine();
        }

        $this->info('✓ Copie terminée avec succès.');
        $this->line('  Connectez-vous sur <fg=cyan>demo.pladigit.fr</> pour vérifier.');

        return Command::SUCCESS;
    }
}
