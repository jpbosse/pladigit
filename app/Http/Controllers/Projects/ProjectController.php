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
        ]);

        /** @var User $user */
        $user = auth()->user();

        $project = Project::create([
            ...$validated,
            'created_by' => $user->id,
            'color' => $validated['color'] ?? '#1E3A5F',
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
            'milestones',
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

        // Vue par jalon : milestones ordonnés par due_date + tâches non rattachées
        // Chaque entrée : ['milestone' => ProjectMilestone|null, 'tasks' => Collection]
        $tasksByMilestone = collect();

        foreach ($project->milestones as $milestone) {
            $milestoneTasks = $allRootTasks->where('milestone_id', $milestone->id)->values();
            $tasksByMilestone->push([
                'milestone' => $milestone,
                'tasks' => $milestoneTasks,
            ]);
        }

        // Tâches sans jalon regroupées en dernier
        $unassignedTasks = $allRootTasks->whereNull('milestone_id')->values();
        if ($unassignedTasks->isNotEmpty()) {
            $tasksByMilestone->push([
                'milestone' => null,
                'tasks' => $unassignedTasks,
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
}
