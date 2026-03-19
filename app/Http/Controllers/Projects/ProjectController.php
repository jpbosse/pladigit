<?php

namespace App\Http\Controllers\Projects;

use App\Enums\ProjectRole;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Project;
use App\Models\Tenant\ProjectMember;
use App\Models\Tenant\User;
use App\Services\AuditService;
use Illuminate\Http\Request;

/**
 * Gestion des projets.
 *
 * Accès :
 *   - index/show : membre du projet (ou Admin/Président/DGS via Policy before())
 *   - create/store : Resp. Direction et au-dessus
 *   - update/delete : owner du projet (ou Admin+)
 */
class ProjectController extends Controller
{
    public function __construct(
        private readonly AuditService $audit,
    ) {}

    /**
     * Liste des projets accessibles pour l'utilisateur courant.
     */
    public function index(Request $request)
    {
        /** @var User $user */
        $user = auth()->user();

        $query = Project::visibleFor($user)
            ->withCount(['tasks', 'projectMembers'])
            ->with(['milestones' => fn ($q) => $q->whereNull('reached_at')->orderBy('due_date')->limit(1)]);

        // Filtres
        if ($status = $request->get('status')) {
            $query->byStatus($status);
        }

        $projects = $query->orderByDesc('updated_at')->paginate(12)->withQueryString();

        return view('projects.index', compact('projects'));
    }

    /**
     * Formulaire de création d'un projet.
     */
    public function create()
    {
        $this->authorize('create', Project::class);

        /** @var User $user */
        $user = auth()->user();

        $members = User::on('tenant')
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'role']);

        return view('projects.create', compact('members'));
    }

    /**
     * Enregistrement d'un nouveau projet.
     * Le créateur est automatiquement ajouté comme owner.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Project::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', 'in:active,on_hold,completed,archived'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_private' => ['sometimes', 'boolean'],
        ]);

        /** @var User $user */
        $user = auth()->user();

        $project = Project::create([
            ...$validated,
            'created_by' => $user->id,
            'color' => $validated['color'] ?? '#1E3A5F',
            'is_private' => $validated['is_private'] ?? false,
        ]);

        // Créateur automatiquement owner
        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'role' => ProjectRole::OWNER->value,
        ]);

        $this->audit->log('project.created', auth()->user(), [
            'project_id' => $project->id,
            'project_name' => $project->name,
        ]);

        return redirect()
            ->route('projects.show', $project)
            ->with('success', 'Projet créé avec succès.');
    }

    /**
     * Détail d'un projet — affiche le shell avec 4 onglets.
     * L'onglet actif est géré par Alpine.js via ?view= dans l'URL.
     */
    public function show(Project $project)
    {
        $this->authorize('view', $project);

        /** @var User $user */
        $user = auth()->user();

        $project->load([
            'projectMembers.user',
            'milestones' => fn ($q) => $q->whereNull('parent_id')->orderBy('sort_order')->orderBy('due_date'),
            'milestones.children' => fn ($q) => $q->orderBy('sort_order')->orderBy('due_date'),
            'budgets',
            'stakeholders.user',
            'commActions.responsible',
            'risks.owner',
            'observations.user',
        ]);

        $taskStats = $project->taskStats();
        $progression = $project->progressionPercent();
        $userRole = $project->memberRole($user);

        // Données finances
        $budgetSummary = $project->budgetSummary();

        // Données conduite du changement
        $activeRisks = $project->activeRisks();
        $criticalRisksCount = $activeRisks->filter(fn ($r) => $r->criticality() === 'critique')->count();

        // Alertes budget (dépassements prévisibles)
        $budgetAlerts = $project->budgets
            ->filter(fn ($b) => $b->variance() > 0)
            ->values();

        // Utilisateurs du tenant pour les selects
        $tenantUsers = \App\Models\Tenant\User::on('tenant')
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'role']);

        // Tâches pour le Kanban : racine uniquement, ordonnées par priorité puis sort_order
        $allRootTasks = $project->rootTasks()
            ->with(['assignee', 'milestone', 'children' => fn ($q) => $q->select('id', 'parent_task_id', 'status')])
            ->orderByRaw("FIELD(priority, 'urgent', 'high', 'medium', 'low')")
            ->orderBy('sort_order')
            ->get();

        // Vue à plat par statut (Kanban classique — conservé pour compatibilité)
        $tasksByStatus = $allRootTasks->groupBy('status');

        // Vue par jalon/phase : structure hiérarchique
        // Chaque entrée phase : ['milestone' => Phase, 'tasks' => Collection, 'children' => [...jalons enfants]]
        // Chaque entrée jalon autonome : ['milestone' => Jalon, 'tasks' => Collection, 'children' => []]
        $tasksByMilestone = collect();

        // Collecter les IDs de tous les jalons enfants pour exclure les tâches orphelines
        $allChildMilestoneIds = $project->milestones->flatMap(fn ($ms) => $ms->children->pluck('id'));

        foreach ($project->milestones as $milestone) {
            if ($milestone->isPhase() && $milestone->children->isNotEmpty()) {
                // Phase avec jalons enfants
                $phaseChildren = collect();
                foreach ($milestone->children as $child) {
                    $childTasks = $allRootTasks->where('milestone_id', $child->id)->values();
                    $phaseChildren->push(['milestone' => $child, 'tasks' => $childTasks]);
                }
                $tasksByMilestone->push([
                    'milestone' => $milestone,
                    'tasks' => collect(), // les tâches sont dans les enfants
                    'children' => $phaseChildren,
                ]);
            } else {
                // Jalon autonome (sans enfants) ou phase sans jalons rattachés
                $milestoneTasks = $allRootTasks->where('milestone_id', $milestone->id)->values();
                $tasksByMilestone->push([
                    'milestone' => $milestone,
                    'tasks' => $milestoneTasks,
                    'children' => collect(),
                ]);
            }
        }

        // Tâches sans jalon regroupées en dernier
        $unassignedTasks = $allRootTasks->whereNull('milestone_id')->values();
        if ($unassignedTasks->isNotEmpty()) {
            $tasksByMilestone->push([
                'milestone' => null,
                'tasks' => $unassignedTasks,
                'children' => collect(),
            ]);
        }

        // Tâches pour le Gantt : toutes celles avec start_date
        $ganttTasks = $project->tasks()
            ->forGantt()
            ->with(['milestone', 'blockedBy:id', 'blocking:id'])
            ->get();

        return view('projects.show', compact(
            'project', 'taskStats', 'progression',
            'userRole', 'tasksByStatus', 'tasksByMilestone', 'ganttTasks',
            'budgetSummary', 'activeRisks', 'criticalRisksCount',
            'budgetAlerts', 'tenantUsers'
        ));
    }

    /**
     * Formulaire d'édition du projet.
     */
    public function edit(Project $project)
    {
        $this->authorize('update', $project);

        return view('projects.edit', compact('project'));
    }

    /**
     * Mise à jour du projet.
     */
    public function update(Request $request, Project $project)
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', 'in:active,on_hold,completed,archived'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_private' => ['sometimes', 'boolean'],
        ]);

        $project->update($validated);

        $this->audit->log('project.updated', auth()->user(), [
            'project_id' => $project->id,
            'project_name' => $project->name,
        ]);

        return redirect()
            ->route('projects.show', $project)
            ->with('success', 'Projet mis à jour.');
    }

    /**
     * Tableau de bord multi-projets — synthèse exécutive transversale.
     *
     * Accessible depuis la liste des projets.
     * Données agrégées sur l'ensemble des projets visibles par l'utilisateur.
     */
    public function dashboard()
    {
        /** @var User $user */
        $user = auth()->user();

        // Tous les projets visibles (hors archivés) avec leurs relations clés
        $projects = Project::visibleFor($user)
            ->whereIn('status', ['draft', 'active', 'on_hold'])
            ->with([
                'milestones',
                'projectMembers',
                'risks',
                'budgets',
            ])
            ->withCount(['tasks', 'projectMembers'])
            ->get();

        // ── KPIs globaux ────────────────────────────────────────────────
        $totalProjects = $projects->count();
        $activeProjects = $projects->where('status', 'active')->count();
        $onHoldProjects = $projects->where('status', 'on_hold')->count();

        // Tâches qui m'appartiennent (assignées) toutes urgences confondues
        $myTasks = \App\Models\Tenant\Task::on('tenant')
            ->where('assigned_to', $user->id)
            ->whereIn('status', ['todo', 'in_progress', 'in_review'])
            ->with(['project:id,name,color', 'milestone:id,title'])
            ->orderByRaw("FIELD(priority,'urgent','high','medium','low')")
            ->orderBy('due_date')
            ->limit(15)
            ->get();

        $myUrgentTasks = $myTasks->whereIn('priority', ['urgent', 'high'])->count();
        $myOverdueTasks = $myTasks->filter(fn ($t) => $t->due_date && $t->due_date->isPast())->count();

        // ── Jalons à venir (30 jours) ───────────────────────────────────
        $upcomingMilestones = \App\Models\Tenant\ProjectMilestone::on('tenant')
            ->whereNull('reached_at')
            ->whereBetween('due_date', [now(), now()->addDays(30)])
            ->with('project:id,name,color')
            ->orderBy('due_date')
            ->get();

        // Jalons en retard (non atteints, date dépassée)
        $lateMilestones = \App\Models\Tenant\ProjectMilestone::on('tenant')
            ->whereNull('reached_at')
            ->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->with('project:id,name,color')
            ->orderBy('due_date')
            ->get();

        // ── Budget global (sur les projets visibles) ────────────────────
        $projectIds = $projects->pluck('id');
        $budgetPlanned = \App\Models\Tenant\ProjectBudget::on('tenant')->whereIn('project_id', $projectIds)->sum('amount_planned');
        $budgetCommitted = \App\Models\Tenant\ProjectBudget::on('tenant')->whereIn('project_id', $projectIds)->sum('amount_committed');
        $budgetPaid = \App\Models\Tenant\ProjectBudget::on('tenant')->whereIn('project_id', $projectIds)->sum('amount_paid');
        $budgetPct = $budgetPlanned > 0 ? round($budgetCommitted / $budgetPlanned * 100) : 0;

        // ── Risques critiques actifs ────────────────────────────────────
        $criticalRisks = \App\Models\Tenant\ProjectRisk::on('tenant')
            ->whereIn('project_id', $projectIds)
            ->whereNotIn('status', ['closed'])
            ->with('project:id,name,color')
            ->get()
            ->filter(fn ($r) => $r->criticality() === 'critique')
            ->values();

        // ── Avancement moyen ────────────────────────────────────────────
        $avgProgression = $projects->isNotEmpty()
            ? (int) round($projects->map->progressionPercent()->average())
            : 0;

        // ── Projets en retard ───────────────────────────────────────────
        $lateProjects = $projects->filter(
            fn ($p) => $p->due_date && $p->due_date->isPast() && $p->status === 'active'
        )->values();

        return view('projects.dashboard', compact(
            'projects',
            'totalProjects', 'activeProjects', 'onHoldProjects',
            'myTasks', 'myUrgentTasks', 'myOverdueTasks',
            'upcomingMilestones', 'lateMilestones',
            'budgetPlanned', 'budgetCommitted', 'budgetPaid', 'budgetPct',
            'criticalRisks',
            'avgProgression',
            'lateProjects'
        ));
    }

    /**
     * Suppression (soft delete) du projet.
     */
    public function destroy(Project $project)
    {
        $this->authorize('delete', $project);

        $project->delete();

        $this->audit->log('project.deleted', auth()->user(), [
            'project_id' => $project->id,
            'project_name' => $project->name,
        ]);

        return redirect()
            ->route('projects.index')
            ->with('success', 'Projet supprimé.');
    }

    /**
     * Duplique un projet — copie la structure (phases, jalons, tâches) sans les données métier
     * (pas de budget, risques, observations — repartir d'une base propre).
     */
    public function duplicate(Request $request, Project $project)
    {
        $this->authorize('view', $project);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'status' => ['sometimes', 'in:draft,active'],
        ]);

        /** @var \App\Models\Tenant\User $user */
        $user = auth()->user();
        $startDate = isset($validated['start_date'])
            ? \Carbon\Carbon::parse($validated['start_date'])
            : now();

        // Décalage entre l'ancienne et la nouvelle date de démarrage
        $offset = $project->start_date
            ? $project->start_date->diffInDays($startDate, false)
            : 0;

        // 1. Créer le projet
        $newProject = Project::create([
            'created_by' => $user->id,
            'name' => $validated['name'],
            'description' => $project->description,
            'status' => $validated['status'] ?? 'draft',
            'start_date' => $startDate,
            'due_date' => $project->due_date?->copy()->addDays($offset),
            'color' => $project->color,
            'is_private' => $project->is_private,
        ]);

        // 2. Owner
        ProjectMember::create([
            'project_id' => $newProject->id,
            'user_id' => $user->id,
            'role' => \App\Enums\ProjectRole::OWNER->value,
        ]);

        // 3. Phases & jalons (deux passes)
        $project->load(['milestones', 'milestones.children']);
        $milestoneMap = [];

        foreach ($project->milestones as $ms) {
            $newMs = \App\Models\Tenant\ProjectMilestone::on('tenant')->create([
                'project_id' => $newProject->id,
                'parent_id' => null,
                'title' => $ms->title,
                'color' => $ms->color,
                'due_date' => $ms->due_date ? $ms->due_date->copy()->addDays($offset) : null,
                'start_date' => $ms->start_date ? $ms->start_date->copy()->addDays($offset) : null,
                'sort_order' => $ms->sort_order,
            ]);
            $milestoneMap[$ms->id] = $newMs->id;

            foreach ($ms->children as $child) {
                $newChild = \App\Models\Tenant\ProjectMilestone::on('tenant')->create([
                    'project_id' => $newProject->id,
                    'parent_id' => $newMs->id,
                    'title' => $child->title,
                    'color' => $child->color,
                    'due_date' => $child->due_date ? $child->due_date->copy()->addDays($offset) : null,
                    'start_date' => $child->start_date ? $child->start_date->copy()->addDays($offset) : null,
                    'sort_order' => $child->sort_order,
                ]);
                $milestoneMap[$child->id] = $newChild->id;
            }
        }

        // 4. Tâches (racines d'abord, puis sous-tâches)
        $srcTasks = \App\Models\Tenant\Task::on('tenant')
            ->where('project_id', $project->id)
            ->whereNull('deleted_at')
            ->orderByRaw('ISNULL(parent_task_id), sort_order')
            ->get();

        $taskMap = [];

        foreach ($srcTasks->whereNull('parent_task_id') as $task) {
            $newTask = \App\Models\Tenant\Task::on('tenant')->create([
                'project_id' => $newProject->id,
                'created_by' => $user->id,
                'milestone_id' => $task->milestone_id ? ($milestoneMap[$task->milestone_id] ?? null) : null,
                'title' => $task->title,
                'description' => $task->description,
                'status' => 'todo',
                'priority' => $task->priority,
                'due_date' => $task->due_date?->copy()->addDays($offset),
                'start_date' => $task->start_date?->copy()->addDays($offset),
                'estimated_hours' => $task->estimated_hours,
                'sort_order' => $task->sort_order,
            ]);
            $taskMap[$task->id] = $newTask->id;
        }

        foreach ($srcTasks->whereNotNull('parent_task_id') as $task) {
            $newTask = \App\Models\Tenant\Task::on('tenant')->create([
                'project_id' => $newProject->id,
                'created_by' => $user->id,
                'parent_task_id' => $taskMap[$task->parent_task_id] ?? null,
                'milestone_id' => $task->milestone_id ? ($milestoneMap[$task->milestone_id] ?? null) : null,
                'title' => $task->title,
                'description' => $task->description,
                'status' => 'todo',
                'priority' => $task->priority,
                'due_date' => $task->due_date?->copy()->addDays($offset),
                'start_date' => $task->start_date?->copy()->addDays($offset),
                'estimated_hours' => $task->estimated_hours,
                'sort_order' => $task->sort_order,
            ]);
            $taskMap[$task->id] = $newTask->id;
        }

        $this->audit->log('project.duplicated', auth()->user(), [
            'source_id' => $project->id,
            'new_id' => $newProject->id,
            'new_name' => $newProject->name,
        ]);

        return redirect()
            ->route('projects.show', $newProject)
            ->with('success', 'Projet dupliqué avec '.count($milestoneMap).' jalons et '.count($taskMap).' tâches.');
    }

    /**
     * Export iCal de l'agenda du projet.
     */
    public function exportIcal(Project $project)
    {
        $this->authorize('exportIcal', $project);

        $events = $project->events()
            ->where('visibility', '!=', 'private')
            ->orWhere('created_by', auth()->id())
            ->get();

        $milestones = $project->milestones()->orderBy('due_date')->get();

        $ical = "BEGIN:VCALENDAR\r\n";
        $ical .= "VERSION:2.0\r\n";
        $ical .= "PRODID:-//Pladigit//Agenda Projet//FR\r\n";
        $ical .= "CALSCALE:GREGORIAN\r\n";
        $ical .= "METHOD:PUBLISH\r\n";

        foreach ($events as $event) {
            $ical .= "BEGIN:VEVENT\r\n";
            $ical .= 'UID:'.$event->id."@pladigit\r\n";
            $ical .= 'DTSTART:'.\Carbon\Carbon::parse($event->starts_at)->format('Ymd\THis')."\r\n";
            $ical .= 'DTEND:'.\Carbon\Carbon::parse($event->ends_at)->format('Ymd\THis')."\r\n";
            $ical .= 'SUMMARY:'.addslashes($event->title)."\r\n";
            if ($event->description) {
                $ical .= 'DESCRIPTION:'.str_replace(["\n", "\r"], ['\n', ''], addslashes($event->description))."\r\n";
            }
            $ical .= "END:VEVENT\r\n";
        }

        foreach ($milestones as $milestone) {
            $ical .= "BEGIN:VEVENT\r\n";
            $ical .= 'UID:milestone-'.$milestone->id."@pladigit\r\n";
            $ical .= 'DTSTART;VALUE=DATE:'.$milestone->due_date->format('Ymd')."\r\n";
            $ical .= 'DTEND;VALUE=DATE:'.$milestone->due_date->addDay()->format('Ymd')."\r\n";
            $ical .= 'SUMMARY:🏁 '.addslashes($milestone->title)."\r\n";
            $ical .= "END:VEVENT\r\n";
        }

        $ical .= "END:VCALENDAR\r\n";

        $slug = \Illuminate\Support\Str::slug($project->name);

        return response($ical, 200, [
            'Content-Type' => 'text/calendar; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$slug}.ics\"",
        ]);
    }

    /**
     * Export PDF du tableau de bord élus.
     * Nécessite : composer require barryvdh/laravel-dompdf
     */
    public function exportPdf(Project $project)
    {
        $this->authorize('view', $project);

        $project->load([
            'projectMembers.user',
            'milestones',
            'milestones.children',
            'budgets',
            'stakeholders.user',
            'commActions.responsible',
            'risks.owner',
            'observations.user',
        ]);

        $taskStats = $project->taskStats();
        $progression = $project->progressionPercent();
        $budgetSummary = $project->budgetSummary();
        $activeRisks = $project->activeRisks();
        $criticalRisksCount = $activeRisks->filter(fn ($r) => $r->criticality() === 'critique')->count();
        $budgetAlerts = $project->budgets->filter(fn ($b) => $b->variance() > 0)->values();

        $data = compact(
            'project', 'taskStats', 'progression',
            'budgetSummary', 'activeRisks', 'criticalRisksCount', 'budgetAlerts'
        );

        // Vérifier que DomPDF est installé
        if (! class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return back()->with('error', 'DomPDF non installé. Lancez : composer require barryvdh/laravel-dompdf');
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('projects.pdf.elus', $data)
            ->setPaper('a4', 'portrait');

        $slug = \Illuminate\Support\Str::slug($project->name);

        return $pdf->download("tableau-bord-elus-{$slug}.pdf");
    }

    /**
     * Export ZIP élus : PDF tableau de bord + CSV tâches + CSV budget.
     * Nécessite : composer require barryvdh/laravel-dompdf
     */
    public function exportZip(Project $project)
    {
        $this->authorize('view', $project);

        $project->load([
            'projectMembers.user',
            'milestones',
            'milestones.children',
            'budgets',
            'stakeholders.user',
            'commActions.responsible',
            'risks.owner',
            'observations.user',
        ]);

        $taskStats = $project->taskStats();
        $progression = $project->progressionPercent();
        $budgetSummary = $project->budgetSummary();
        $activeRisks = $project->activeRisks();
        $criticalRisksCount = $activeRisks->filter(fn ($r) => $r->criticality() === 'critique')->count();
        $budgetAlerts = $project->budgets->filter(fn ($b) => $b->variance() > 0)->values();

        $slug = \Illuminate\Support\Str::slug($project->name);
        $tmpDir = storage_path("app/private/tmp/zip_exports/{$slug}_".time());
        mkdir($tmpDir, 0775, true);

        try {
            // 1. PDF tableau de bord
            if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
                $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('projects.pdf.elus', compact(
                    'project', 'taskStats', 'progression',
                    'budgetSummary', 'activeRisks', 'criticalRisksCount', 'budgetAlerts'
                ))->setPaper('a4', 'portrait');
                file_put_contents("{$tmpDir}/tableau-bord-elus.pdf", $pdf->output());
            }

            // 2. CSV tâches
            $tasks = $project->tasks()
                ->with(['assignee:id,name', 'milestone:id,title'])
                ->whereNull('parent_task_id')
                ->orderBy('sort_order')
                ->get();

            $csvTasks = "Titre,Statut,Priorité,Assigné,Jalon,Début,Échéance,Heures estimées,Heures réalisées\n";
            foreach ($tasks as $t) {
                $csvTasks .= implode(',', array_map(fn ($v) => '"'.str_replace('"', '""', (string) $v).'"', [
                    $t->title,
                    \App\Models\Tenant\Task::statusLabels()[$t->status] ?? $t->status,
                    \App\Models\Tenant\Task::priorityLabels()[$t->priority] ?? $t->priority,
                    ($t->assignee !== null ? $t->assignee->name : ''),
                    ($t->milestone !== null ? $t->milestone->title : ''),
                    $t->start_date ? $t->start_date->format('d/m/Y') : '',
                    $t->due_date ? $t->due_date->format('d/m/Y') : '',
                    $t->estimated_hours ?? '',
                    $t->actual_hours ?? '',
                ]))."\n";
            }
            file_put_contents("{$tmpDir}/taches.csv", "\xEF\xBB\xBF".$csvTasks); // BOM UTF-8 pour Excel

            // 3. CSV budget
            $csvBudget = "Type,Libellé,Année,Prévu,Engagé,Mandaté,Cofinanceur,Taux cofinancement\n";
            foreach ($project->budgets as $b) {
                $csvBudget .= implode(',', array_map(fn ($v) => '"'.str_replace('"', '""', (string) $v).'"', [
                    $b->type === 'invest' ? 'Investissement' : 'Fonctionnement',
                    $b->label,
                    $b->year,
                    number_format($b->amount_planned, 2, ',', ' '),
                    number_format($b->amount_committed, 2, ',', ' '),
                    number_format($b->amount_paid, 2, ',', ' '),
                    $b->cofinancer ?? '',
                    $b->cofinancing_rate ? $b->cofinancing_rate.'%' : '',
                ]))."\n";
            }
            file_put_contents("{$tmpDir}/budget.csv", "\xEF\xBB\xBF".$csvBudget);

            // 4. CSV risques
            $csvRisques = "Titre,Catégorie,Probabilité,Impact,Criticité,Statut,Plan de mitigation\n";
            foreach ($activeRisks as $r) {
                $csvRisques .= implode(',', array_map(fn ($v) => '"'.str_replace('"', '""', (string) $v).'"', [
                    $r->title,
                    $r->category,
                    $r->probability,
                    $r->impact,
                    $r->criticality(),
                    $r->status,
                    $r->mitigation_plan ?? '',
                ]))."\n";
            }
            file_put_contents("{$tmpDir}/risques.csv", "\xEF\xBB\xBF".$csvRisques);

            // 5. Créer le ZIP
            $zipPath = storage_path("app/private/tmp/rapport-{$slug}.zip");
            $zip = new \ZipArchive;
            $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

            foreach (glob("{$tmpDir}/*") as $file) {
                $zip->addFile($file, basename($file));
            }
            $zip->close();

            // Nettoyer le répertoire temporaire
            array_map('unlink', glob("{$tmpDir}/*"));
            rmdir($tmpDir);

            return response()->download($zipPath, "rapport-{$slug}.zip")->deleteFileAfterSend(true);

        } catch (\Throwable $e) {
            // Nettoyage en cas d'erreur
            if (is_dir($tmpDir)) {
                array_map('unlink', glob("{$tmpDir}/*"));
                rmdir($tmpDir);
            }
            \Illuminate\Support\Facades\Log::error('exportZip failed', ['error' => $e->getMessage(), 'project_id' => $project->id]);

            return back()->with('error', 'Erreur lors de la génération du ZIP : '.$e->getMessage());
        }
    }
}
