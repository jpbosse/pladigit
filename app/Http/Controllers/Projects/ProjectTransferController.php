<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Project;
use App\Models\Tenant\ProjectBudget;
use App\Models\Tenant\ProjectCommAction;
use App\Models\Tenant\ProjectMember;
use App\Models\Tenant\ProjectMilestone;
use App\Models\Tenant\ProjectObservation;
use App\Models\Tenant\ProjectRisk;
use App\Models\Tenant\ProjectStakeholder;
use App\Models\Tenant\Task;
use App\Models\Tenant\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Export / Import complet d'un projet au format JSON.
 *
 * Export : sérialise toutes les données du projet (jalons, tâches, budget,
 *          parties prenantes, plan de com, risques, observations, membres).
 *          Les utilisateurs sont référencés par email pour portabilité.
 *
 * Import : recrée le projet sur un autre tenant, en recherchant les
 *          utilisateurs par email et en les ignorant s'ils n'existent pas.
 */
class ProjectTransferController extends Controller
{
    private const EXPORT_VERSION = '1.0';

    public function __construct(
        private readonly AuditService $audit,
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // EXPORT
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Génère un fichier JSON contenant l'intégralité du projet.
     */
    public function export(Project $project)
    {
        $this->authorize('view', $project);

        $project->load([
            'projectMembers.user',
            'milestones.children.children.children',
            'milestones.tasks',
            'budgets',
            'stakeholders.user',
            'commActions.responsible',
            'risks.owner',
            'observations.user',
            'documents',
        ]);

        // Toutes les tâches racine avec sous-tâches
        $tasks = $project->tasks()
            ->whereNull('parent_task_id')
            ->with(['assignee', 'milestone', 'children.children', 'children.assignee'])
            ->get();

        $payload = [
            '_meta' => [
                'version' => self::EXPORT_VERSION,
                'exported_at' => now()->toIso8601String(),
                'source_app' => config('app.url'),
            ],
            'project' => $this->serializeProject($project),
            'members' => $this->serializeMembers($project->projectMembers),
            'milestones' => $this->serializeMilestones(
                $project->milestones->whereNull('parent_id')
            ),
            'tasks' => $this->serializeTasks($tasks),
            'budgets' => $this->serializeBudgets($project->budgets),
            'stakeholders' => $this->serializeStakeholders($project->stakeholders),
            'comm_actions' => $this->serializeCommActions($project->commActions),
            'risks' => $this->serializeRisks($project->risks),
            'observations' => $this->serializeObservations($project->observations),
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $filename = 'projet-'.\Str::slug($project->name).'-'.now()->format('Ymd-Hi').'.json';

        return response($json, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // IMPORT — formulaire
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Affiche le formulaire d'import.
     */
    public function importForm()
    {
        $this->authorize('create', Project::class);

        return view('projects.import');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // IMPORT — traitement
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Traite le fichier JSON uploadé et recrée le projet.
     */
    public function importStore(Request $request)
    {
        $this->authorize('create', Project::class);

        $request->validate([
            'file' => ['required', 'file', 'mimetypes:application/json,text/plain', 'max:5120'],
            'project_name' => ['nullable', 'string', 'max:255'],
        ]);

        $json = file_get_contents($request->file('file')->getRealPath());
        $data = json_decode($json, true);

        if (! $data || ! isset($data['_meta'], $data['project'])) {
            return back()->withErrors(['file' => 'Fichier JSON invalide ou corrompu.']);
        }

        if (($data['_meta']['version'] ?? '') !== self::EXPORT_VERSION) {
            return back()->withErrors(['file' => 'Version du fichier incompatible (attendu v'.self::EXPORT_VERSION.').']);
        }

        /** @var \App\Models\Tenant\User $currentUser */
        $currentUser = auth()->user();

        // Construire un mapping email → User pour ce tenant
        $usersByEmail = User::on('tenant')
            ->where('status', 'active')
            ->get()
            ->keyBy(fn (User $u) => strtolower($u->email));

        // ── Créer le projet ───────────────────────────────────────────────
        $pd = $data['project'];
        $project = Project::create([
            'name' => $request->filled('project_name') ? $request->project_name : ($pd['name'].' (importé)'),
            'description' => $pd['description'] ?? null,
            'status' => in_array($pd['status'] ?? '', ['active', 'on_hold', 'completed', 'archived', 'draft'])
                             ? $pd['status'] : 'active',
            'start_date' => $pd['start_date'] ?? null,
            'due_date' => $pd['due_date'] ?? null,
            'color' => $pd['color'] ?? '#1E3A5F',
            'is_private' => $pd['is_private'] ?? false,
            'created_by' => $currentUser->id,
        ]);

        // L'importateur est owner
        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $currentUser->id,
            'role' => 'owner',
        ]);

        $importLog = ['imported_members' => 0, 'skipped_members' => [], 'skipped_owners' => []];

        // ── Membres ───────────────────────────────────────────────────────
        foreach ($data['members'] ?? [] as $m) {
            $email = strtolower($m['email'] ?? '');
            if (! $email) {
                continue;
            }
            if (strtolower($email) === strtolower($currentUser->email)) {
                continue; // déjà ajouté comme owner
            }
            $user = $usersByEmail->get($email);
            if (! $user) {
                $importLog['skipped_members'][] = $m['name'].' ('.$email.')';

                continue;
            }
            // Ne pas dupliquer
            if ($project->projectMembers()->where('user_id', $user->id)->exists()) {
                continue;
            }
            $role = in_array($m['role'] ?? '', ['owner', 'member', 'viewer']) ? $m['role'] : 'member';
            // L'importateur est déjà owner — transformer les autres "owner" en "member"
            if ($role === 'owner') {
                $importLog['skipped_owners'][] = $m['name'];
                $role = 'member';
            }
            ProjectMember::create(['project_id' => $project->id, 'user_id' => $user->id, 'role' => $role]);
            $importLog['imported_members']++;
        }

        // ── Jalons (récursif) ─────────────────────────────────────────────
        // Map : ancien ID (string, de l'export) → nouveau ID (int, DB)
        $milestoneIdMap = [];
        $this->importMilestones($data['milestones'] ?? [], $project->id, null, $milestoneIdMap);

        // ── Tâches (récursif, après jalons pour avoir le mapping) ─────────
        $taskIdMap = [];
        $this->importTasks($data['tasks'] ?? [], $project->id, null, $milestoneIdMap, $usersByEmail, $taskIdMap);

        // ── Budget ────────────────────────────────────────────────────────
        foreach ($data['budgets'] ?? [] as $b) {
            ProjectBudget::create([
                'project_id' => $project->id,
                'type' => in_array($b['type'] ?? '', ['invest', 'fonct']) ? $b['type'] : 'fonct',
                'label' => $b['label'] ?? '—',
                'year' => $b['year'] ?? now()->year,
                'amount_planned' => $b['amount_planned'] ?? 0,
                'amount_committed' => $b['amount_committed'] ?? 0,
                'amount_paid' => $b['amount_paid'] ?? 0,
                'cofinancer' => $b['cofinancer'] ?? null,
                'cofinancing_rate' => $b['cofinancing_rate'] ?? null,
                'notes' => $b['notes'] ?? null,
                'created_by' => $currentUser->id,
            ]);
        }

        // ── Parties prenantes ─────────────────────────────────────────────
        foreach ($data['stakeholders'] ?? [] as $s) {
            $user = isset($s['email']) ? ($usersByEmail->get(strtolower($s['email'])) ?? null) : null;
            ProjectStakeholder::create([
                'project_id' => $project->id,
                'user_id' => $user?->id,
                'name' => $s['name'] ?? '—',
                'role' => $s['role'] ?? null,
                'adhesion' => in_array($s['adhesion'] ?? '', ['champion', 'supporter', 'neutre', 'vigilant', 'resistant'])
                                ? $s['adhesion'] : 'neutre',
                'influence' => in_array($s['influence'] ?? '', ['high', 'medium', 'low'])
                                ? $s['influence'] : 'medium',
                'notes' => $s['notes'] ?? null,
            ]);
        }

        // ── Plan de communication ─────────────────────────────────────────
        foreach ($data['comm_actions'] ?? [] as $ca) {
            $responsible = isset($ca['responsible_email'])
                ? ($usersByEmail->get(strtolower($ca['responsible_email'])) ?? null)
                : null;
            ProjectCommAction::create([
                'project_id' => $project->id,
                'title' => $ca['title'] ?? '—',
                'target_audience' => $ca['target_audience'] ?? null,
                'channel' => in_array($ca['channel'] ?? '', ['email', 'reunion', 'affichage', 'courrier', 'intranet', 'autre'])
                                       ? $ca['channel'] : 'autre',
                'message' => $ca['message'] ?? null,
                'resources_needed' => $ca['resources_needed'] ?? null,
                'planned_at' => $ca['planned_at'] ?? null,
                'done_at' => null, // ne pas importer le statut "fait"
                'responsible_id' => $responsible?->id,
                'notes' => $ca['notes'] ?? null,
            ]);
        }

        // ── Risques ───────────────────────────────────────────────────────
        foreach ($data['risks'] ?? [] as $r) {
            $owner = isset($r['owner_email'])
                ? ($usersByEmail->get(strtolower($r['owner_email'])) ?? null)
                : null;
            ProjectRisk::create([
                'project_id' => $project->id,
                'title' => $r['title'] ?? '—',
                'description' => $r['description'] ?? null,
                'category' => in_array($r['category'] ?? '', ['humain', 'technique', 'budget', 'planning', 'juridique', 'autre'])
                                      ? $r['category'] : 'autre',
                'probability' => in_array($r['probability'] ?? '', ['low', 'medium', 'high'])
                                      ? $r['probability'] : 'medium',
                'impact' => in_array($r['impact'] ?? '', ['low', 'medium', 'high', 'critical'])
                                      ? $r['impact'] : 'medium',
                'status' => in_array($r['status'] ?? '', ['identified', 'monitored', 'mitigated', 'closed'])
                                      ? $r['status'] : 'identified',
                'mitigation_plan' => $r['mitigation_plan'] ?? null,
                'owner_id' => $owner?->id,
            ]);
        }

        // ── Observations ──────────────────────────────────────────────────
        foreach ($data['observations'] ?? [] as $o) {
            $user = isset($o['user_email'])
                ? ($usersByEmail->get(strtolower($o['user_email'])) ?? $currentUser)
                : $currentUser;
            ProjectObservation::create([
                'project_id' => $project->id,
                'user_id' => $user->id,
                'body' => $o['body'] ?? '',
                'type' => in_array($o['type'] ?? '', ['observation', 'question', 'validation', 'alerte'])
                                ? $o['type'] : 'observation',
            ]);
        }

        $this->audit->log('project.imported', $currentUser, [
            'new' => ['project_id' => $project->id, 'project_name' => $project->name],
        ]);

        $warnings = [];
        if (! empty($importLog['skipped_members'])) {
            $warnings[] = 'Membres introuvables (non ajoutés) : '.implode(', ', $importLog['skipped_members']);
        }
        if (! empty($importLog['skipped_owners'])) {
            $warnings[] = 'Propriétaires rétrogradés en membre : '.implode(', ', $importLog['skipped_owners']);
        }

        $message = 'Projet importé avec succès.';
        if ($warnings) {
            $message .= ' ⚠ '.implode(' | ', $warnings);
        }

        return redirect()
            ->route('projects.show', $project)
            ->with('success', $message);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Méthodes privées — sérialisation
    // ─────────────────────────────────────────────────────────────────────────

    /** @param \Illuminate\Database\Eloquent\Collection<int, ProjectMember> $members */
    private function serializeMembers($members): array
    {
        return $members->map(fn ($pm) => [
            'name' => $pm->user->name,
            'email' => $pm->user->email,
            'role' => $pm->role,
        ])->values()->all();
    }

    /** @param \Illuminate\Support\Collection<int, ProjectMilestone> $milestones */
    private function serializeMilestones($milestones): array
    {
        return $milestones->map(fn (ProjectMilestone $ms) => [
            'id' => $ms->id,
            'node_type' => $ms->node_type,
            'title' => $ms->title,
            'description' => $ms->description,
            'start_date' => $ms->start_date !== null ? $ms->start_date->toDateString() : null,
            'due_date' => $ms->due_date !== null ? $ms->due_date->toDateString() : null,
            'reached_at' => $ms->reached_at !== null ? $ms->reached_at->toIso8601String() : null,
            'color' => $ms->color,
            'sort_order' => $ms->sort_order,
            'manual_progress' => $ms->manual_progress,
            'comment' => $ms->comment,
            'children' => $this->serializeMilestones($ms->children),
        ])->values()->all();
    }

    /** @param \Illuminate\Database\Eloquent\Collection<int, Task> $tasks */
    private function serializeTasks($tasks): array
    {
        return $tasks->map(fn (Task $t) => [
            'id' => $t->id,
            'title' => $t->title,
            'description' => $t->description,
            'status' => $t->status,
            'priority' => $t->priority,
            'start_date' => $t->start_date?->toDateString(),
            'due_date' => $t->due_date?->toDateString(),
            'estimated_hours' => $t->estimated_hours,
            'actual_hours' => $t->actual_hours,
            'assigned_email' => $t->assignee?->email,
            'milestone_id' => $t->milestone_id, // sera re-mappé à l'import
            'sort_order' => $t->sort_order,
            'children' => $this->serializeTasks($t->children),
        ])->values()->all();
    }

    /** @param \Illuminate\Database\Eloquent\Collection<int, ProjectBudget> $budgets */
    private function serializeBudgets($budgets): array
    {
        return $budgets->map(fn (ProjectBudget $b) => [
            'type' => $b->type,
            'label' => $b->label,
            'year' => $b->year,
            'amount_planned' => $b->amount_planned,
            'amount_committed' => $b->amount_committed,
            'amount_paid' => $b->amount_paid,
            'cofinancer' => $b->cofinancer,
            'cofinancing_rate' => $b->cofinancing_rate,
            'notes' => $b->notes,
        ])->values()->all();
    }

    /** @param \Illuminate\Database\Eloquent\Collection<int, ProjectStakeholder> $stakeholders */
    private function serializeStakeholders($stakeholders): array
    {
        return $stakeholders->map(fn (ProjectStakeholder $s) => [
            'name' => $s->name ?? $s->user?->name,
            'email' => $s->user?->email,
            'role' => $s->role,
            'adhesion' => $s->adhesion,
            'influence' => $s->influence,
            'notes' => $s->notes,
        ])->values()->all();
    }

    /** @param \Illuminate\Database\Eloquent\Collection<int, ProjectCommAction> $actions */
    private function serializeCommActions($actions): array
    {
        return $actions->map(fn (ProjectCommAction $ca) => [
            'title' => $ca->title,
            'target_audience' => $ca->target_audience,
            'channel' => $ca->channel,
            'message' => $ca->message,
            'resources_needed' => $ca->resources_needed,
            'planned_at' => $ca->planned_at !== null ? $ca->planned_at->toDateString() : null,
            'done_at' => $ca->done_at !== null ? $ca->done_at->toDateString() : null,
            'responsible_email' => $ca->responsible?->email,
            'notes' => $ca->notes,
        ])->values()->all();
    }

    /** @param \Illuminate\Database\Eloquent\Collection<int, ProjectRisk> $risks */
    private function serializeRisks($risks): array
    {
        return $risks->map(fn (ProjectRisk $r) => [
            'title' => $r->title,
            'description' => $r->description,
            'category' => $r->category,
            'probability' => $r->probability,
            'impact' => $r->impact,
            'status' => $r->status,
            'mitigation_plan' => $r->mitigation_plan,
            'owner_email' => $r->owner?->email,
        ])->values()->all();
    }

    /** @param \Illuminate\Database\Eloquent\Collection<int, ProjectObservation> $obs */
    private function serializeObservations($obs): array
    {
        return $obs->map(fn (ProjectObservation $o) => [
            'body' => $o->body,
            'type' => $o->type,
            'user_email' => $o->user?->email,
            'created_at' => $o->created_at?->toIso8601String(),
        ])->values()->all();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Méthodes privées — import récursif
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Importe récursivement les jalons et construit le mapping ancien ID → nouveau ID.
     *
     * @param  array<int, array<string, mixed>>  $milestones
     * @param  array<int|string, int>  &$idMap
     */
    private function importMilestones(array $milestones, int $projectId, ?int $parentId, array &$idMap): void
    {
        foreach ($milestones as $m) {
            $ms = ProjectMilestone::create([
                'project_id' => $projectId,
                'parent_id' => $parentId,
                'node_type' => $m['node_type'] ?? null,
                'title' => $m['title'] ?? '—',
                'description' => $m['description'] ?? null,
                'start_date' => $m['start_date'] ?? null,
                'due_date' => $m['due_date'] ?? null,
                'reached_at' => null, // ne pas importer l'état "atteint"
                'color' => $m['color'] ?? '#1E3A5F',
                'sort_order' => $m['sort_order'] ?? 10,
                'manual_progress' => $m['manual_progress'] ?? null,
                'comment' => $m['comment'] ?? null,
            ]);

            if (isset($m['id'])) {
                $idMap[(int) $m['id']] = $ms->id;
            }

            if (! empty($m['children'])) {
                $this->importMilestones($m['children'], $projectId, $ms->id, $idMap);
            }
        }
    }

    /**
     * Importe récursivement les tâches.
     *
     * @param  array<int, array<string, mixed>>  $tasks
     * @param  \Illuminate\Support\Collection<string, User>  $usersByEmail
     * @param  array<int|string, int>  &$milestoneIdMap
     * @param  array<int|string, int>  &$taskIdMap
     */
    private function importTasks(
        array $tasks,
        int $projectId,
        ?int $parentTaskId,
        array $milestoneIdMap,
        Collection $usersByEmail,
        array &$taskIdMap
    ): void {
        foreach ($tasks as $t) {
            $assignee = isset($t['assigned_email'])
                ? ($usersByEmail->get(strtolower($t['assigned_email'])) ?? null)
                : null;

            $newMilestoneId = isset($t['milestone_id'])
                ? ($milestoneIdMap[(int) $t['milestone_id']] ?? null)
                : null;

            $task = Task::create([
                'project_id' => $projectId,
                'parent_task_id' => $parentTaskId,
                'milestone_id' => $newMilestoneId,
                'title' => $t['title'] ?? '—',
                'description' => $t['description'] ?? null,
                'status' => in_array($t['status'] ?? '', ['todo', 'in_progress', 'in_review', 'done'])
                                     ? $t['status'] : 'todo',
                'priority' => in_array($t['priority'] ?? '', ['low', 'medium', 'high', 'urgent'])
                                     ? $t['priority'] : 'medium',
                'start_date' => $t['start_date'] ?? null,
                'due_date' => $t['due_date'] ?? null,
                'estimated_hours' => $t['estimated_hours'] ?? null,
                'actual_hours' => $t['actual_hours'] ?? null,
                'assigned_to' => $assignee?->id,
                'sort_order' => $t['sort_order'] ?? 0,
                'created_by' => auth()->id(),
            ]);

            if (isset($t['id'])) {
                $taskIdMap[(int) $t['id']] = $task->id;
            }

            if (! empty($t['children'])) {
                $this->importTasks($t['children'], $projectId, $task->id, $milestoneIdMap, $usersByEmail, $taskIdMap);
            }
        }
    }

    private function serializeProject(Project $project): array
    {
        return [
            'name' => $project->name,
            'description' => $project->description,
            'status' => $project->status,
            'start_date' => $project->start_date?->toDateString(),
            'due_date' => $project->due_date?->toDateString(),
            'color' => $project->color,
            'is_private' => $project->is_private,
        ];
    }
}
