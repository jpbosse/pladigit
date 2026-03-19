<?php

namespace App\Http\Controllers\Projects;

use App\Enums\ProjectRole;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Project;
use App\Models\Tenant\ProjectMember;
use App\Models\Tenant\ProjectTemplate;
use App\Services\AuditService;
use Illuminate\Http\Request;

/**
 * Gestion des modèles de projets (templates).
 *
 * Accès :
 *   - index/show : tous les utilisateurs authentifiés
 *   - create/store/update/delete : Resp. Direction et au-dessus
 *   - apply : Resp. Direction et au-dessus (crée un nouveau projet)
 */
class ProjectTemplateController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    /**
     * Liste des templates disponibles.
     */
    public function index()
    {
        $templates = ProjectTemplate::on('tenant')
            ->with('creator:id,name')
            ->orderBy('name')
            ->get();

        return view('projects.templates.index', compact('templates'));
    }

    /**
     * Formulaire de création.
     */
    public function create()
    {
        $this->authorizeCreate();

        return view('projects.templates.create');
    }

    /**
     * Enregistrement d'un nouveau template.
     */
    public function store(Request $request)
    {
        $this->authorizeCreate();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $template = ProjectTemplate::on('tenant')->create([
            ...$validated,
            'color' => $validated['color'] ?? '#1E3A5F',
            'created_by' => auth()->id(),
            'milestone_templates' => [],
            'task_templates' => [],
        ]);

        $this->audit->log('template.created', auth()->user(), [
            'template_id' => $template->id,
            'template_name' => $template->name,
        ]);

        return redirect()
            ->route('projects.templates.edit', $template)
            ->with('success', 'Modèle créé. Ajoutez maintenant des phases, jalons et tâches.');
    }

    /**
     * Formulaire d'édition (structure phases/jalons/tâches).
     */
    public function edit(ProjectTemplate $template)
    {
        $this->authorizeCreate();

        return view('projects.templates.edit', compact('template'));
    }

    /**
     * Mise à jour du template (infos + structure JSON).
     */
    public function update(Request $request, ProjectTemplate $template)
    {
        $this->authorizeCreate();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'milestone_templates' => ['nullable', 'json'],
            'task_templates' => ['nullable', 'json'],
        ]);

        $template->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'color' => $validated['color'] ?? $template->color,
            'milestone_templates' => isset($validated['milestone_templates'])
                ? json_decode($validated['milestone_templates'], true)
                : $template->milestone_templates,
            'task_templates' => isset($validated['task_templates'])
                ? json_decode($validated['task_templates'], true)
                : $template->task_templates,
        ]);

        $this->audit->log('template.updated', auth()->user(), [
            'template_id' => $template->id,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Modèle mis à jour.');
    }

    /**
     * Suppression du template.
     */
    public function destroy(ProjectTemplate $template)
    {
        $this->authorizeCreate();

        $template->delete();

        $this->audit->log('template.deleted', auth()->user(), [
            'template_id' => $template->id,
            'template_name' => $template->name,
        ]);

        return redirect()
            ->route('projects.templates.index')
            ->with('success', 'Modèle supprimé.');
    }

    /**
     * Applique un template : crée un nouveau projet avec la structure du template.
     */
    public function apply(Request $request, ProjectTemplate $template)
    {
        $this->authorizeCreate();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'status' => ['required', 'in:active,on_hold,draft'],
        ]);

        $startDate = \Carbon\Carbon::parse($validated['start_date']);

        // Créer le projet
        $project = Project::on('tenant')->create([
            'created_by' => auth()->id(),
            'name' => $validated['name'],
            'description' => $template->description,
            'status' => $validated['status'],
            'start_date' => $startDate,
            'color' => $template->color,
            'is_private' => false,
        ]);

        // Créateur = owner
        ProjectMember::on('tenant')->create([
            'project_id' => $project->id,
            'user_id' => auth()->id(),
            'role' => ProjectRole::OWNER->value,
        ]);

        // Appliquer la structure du template
        $result = $template->applyTo($project, $startDate);

        $this->audit->log('template.applied', auth()->user(), [
            'template_id' => $template->id,
            'project_id' => $project->id,
            'project_name' => $project->name,
            'milestones' => $result['milestones'],
            'tasks' => $result['tasks'],
        ]);

        return redirect()
            ->route('projects.show', $project)
            ->with('success', "Projet créé depuis le modèle « {$template->name} » — {$result['milestones']} jalons et {$result['tasks']} tâches générés.");
    }

    /**
     * Crée un template depuis un projet existant (capture la structure).
     */
    public function fromProject(Request $request, Project $project)
    {
        $this->authorizeCreate();
        $this->authorize('view', $project);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        // Charger les jalons et tâches du projet
        $milestones = \App\Models\Tenant\ProjectMilestone::on('tenant')
            ->where('project_id', $project->id)
            ->whereNull('parent_id')
            ->with('children')
            ->orderBy('sort_order')
            ->get();

        $tasks = \App\Models\Tenant\Task::on('tenant')
            ->where('project_id', $project->id)
            ->whereNull('parent_task_id')
            ->whereNull('deleted_at')
            ->orderBy('sort_order')
            ->get();

        // Construire milestone_templates
        $msTemplates = [];
        $msIndexMap = []; // id → index dans msTemplates

        foreach ($milestones as $ms) {
            $idx = count($msTemplates);
            $msTemplates[] = [
                'title' => $ms->title,
                'color' => $ms->color,
                'offset_days' => $project->start_date
                    ? $project->start_date->diffInDays($ms->due_date)
                    : 30,
                'parent_index' => null,
            ];
            $msIndexMap[$ms->id] = $idx;

            foreach ($ms->children as $child) {
                $cidx = count($msTemplates);
                $msTemplates[] = [
                    'title' => $child->title,
                    'color' => $child->color,
                    'offset_days' => $project->start_date
                        ? $project->start_date->diffInDays($child->due_date)
                        : 30,
                    'parent_index' => $idx,
                ];
                $msIndexMap[$child->id] = $cidx;
            }
        }

        // Construire task_templates
        $taskTemplates = [];
        foreach ($tasks as $task) {
            $taskTemplates[] = [
                'title' => $task->title,
                'description' => $task->description,
                'priority' => $task->priority,
                'estimated_hours' => $task->estimated_hours,
                'offset_days' => $project->start_date && $task->due_date
                    ? $project->start_date->diffInDays($task->due_date)
                    : 7,
                'milestone_index' => $task->milestone_id
                    ? ($msIndexMap[$task->milestone_id] ?? null)
                    : null,
            ];
        }

        $template = ProjectTemplate::on('tenant')->create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? $project->description,
            'color' => $project->color,
            'created_by' => auth()->id(),
            'milestone_templates' => $msTemplates,
            'task_templates' => $taskTemplates,
        ]);

        $this->audit->log('template.from_project', auth()->user(), [
            'template_id' => $template->id,
            'project_id' => $project->id,
        ]);

        return redirect()
            ->route('projects.templates.edit', $template)
            ->with('success', "Modèle « {$template->name} » créé depuis le projet — ".count($msTemplates).' jalons, '.count($taskTemplates).' tâches.');
    }

    private function authorizeCreate(): void
    {
        /** @var \App\Models\Tenant\User|null $user */
        $user = auth()->user();
        $role = \App\Enums\UserRole::tryFrom($user !== null ? ($user->role ?? '') : '');
        abort_unless($role && $role->atLeast(\App\Enums\UserRole::RESP_DIRECTION), 403);
    }
}
