<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modèle de projet réutilisable.
 *
 * Capture la structure type d'un projet :
 *   - Phases & jalons avec offsets en jours depuis le démarrage
 *   - Tâches types avec offset, priorité, heures estimées
 *
 * Quand on applique un template à un nouveau projet, les dates sont
 * calculées à partir de la date de démarrage choisie :
 *   due_date = start_date + offset_days
 *
 * Structure milestone_templates :
 *   [{ title, color, offset_days, is_phase, parent_index }]
 *   parent_index = index dans le même tableau (pour les jalons enfants d'une phase)
 *
 * Structure task_templates :
 *   [{ title, description, priority, offset_days, estimated_hours, milestone_index }]
 *   milestone_index = index dans milestone_templates
 */
class ProjectTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        'name',
        'description',
        'color',
        'task_templates',
        'milestone_templates',
        'created_by',
    ];

    protected $casts = [
        'task_templates' => 'array',
        'milestone_templates' => 'array',
    ];

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Nombre de phases/jalons dans ce template.
     */
    public function milestoneCount(): int
    {
        return count($this->milestone_templates ?? []);
    }

    /**
     * Nombre de tâches dans ce template.
     */
    public function taskCount(): int
    {
        return count($this->task_templates ?? []);
    }

    /**
     * Applique ce template à un projet existant à partir d'une date de démarrage.
     * Crée les phases, jalons et tâches avec les dates calculées.
     *
     * @return array{milestones: int, tasks: int}
     */
    public function applyTo(Project $project, \Carbon\Carbon $startDate): array
    {
        $milestoneMap = []; // index template → id créé
        $msCount = 0;
        $taskCount = 0;

        // 1. Créer les phases et jalons
        foreach ($this->milestone_templates ?? [] as $idx => $ms) {
            $parentId = null;

            // Jalon enfant d'une phase
            if (isset($ms['parent_index'])) {
                $parentId = $milestoneMap[$ms['parent_index']] ?? null;
            }

            $created = ProjectMilestone::on('tenant')->create([
                'project_id' => $project->id,
                'parent_id' => $parentId,
                'title' => $ms['title'],
                'description' => $ms['description'] ?? null,
                'color' => $ms['color'] ?? '#EA580C',
                'due_date' => $startDate->copy()->addDays($ms['offset_days'] ?? 30),
                'start_date' => isset($ms['start_offset_days'])
                    ? $startDate->copy()->addDays($ms['start_offset_days'])
                    : null,
                'sort_order' => ($idx + 1) * 10,
            ]);

            $milestoneMap[$idx] = $created->id;
            $msCount++;
        }

        // 2. Créer les tâches
        foreach ($this->task_templates ?? [] as $task) {
            $milestoneId = null;
            if (isset($task['milestone_index'])) {
                $milestoneId = $milestoneMap[$task['milestone_index']] ?? null;
            }

            Task::on('tenant')->create([
                'project_id' => $project->id,
                'created_by' => $project->created_by,
                'milestone_id' => $milestoneId,
                'title' => $task['title'],
                'description' => $task['description'] ?? null,
                'status' => 'todo',
                'priority' => $task['priority'] ?? 'medium',
                'due_date' => $startDate->copy()->addDays($task['offset_days'] ?? 7),
                'estimated_hours' => $task['estimated_hours'] ?? null,
                'sort_order' => 0,
            ]);

            $taskCount++;
        }

        return ['milestones' => $msCount, 'tasks' => $taskCount];
    }
}
