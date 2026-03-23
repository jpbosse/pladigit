<?php

namespace App\Console\Commands;

use App\Models\Platform\Organization;
use App\Models\Tenant\Task;
use App\Services\TenantManager;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * php artisan pladigit:generate-recurring-tasks [--tenant=slug]
 * Planifié quotidiennement à 06h00 via routes/console.php.
 * Génère les occurrences futures des tâches récurrentes (horizon 4 semaines).
 */
class GenerateRecurringTasks extends Command
{
    protected $signature = 'pladigit:generate-recurring-tasks {--tenant= : Cibler un tenant spécifique}';

    protected $description = 'Génère les occurrences de tâches récurrentes';

    public function __construct(private readonly TenantManager $tenantManager)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $slug = $this->option('tenant');
        $query = Organization::where('status', 'active');

        if ($slug) {
            $query->where('slug', $slug);
            if ($query->doesntExist()) {
                $this->error("Tenant « {$slug} » introuvable ou inactif.");

                return self::FAILURE;
            }
        }

        $generated = 0;
        foreach ($query->get() as $org) {
            $this->tenantManager->connectTo($org);

            $parents = Task::on('tenant')
                ->whereNotNull('recurrence_type')
                ->whereNull('recurrence_parent_id')
                ->whereNull('deleted_at')
                ->get();

            foreach ($parents as $parent) {
                $generated += $this->generateNext($parent);
            }
        }

        $this->info("Occurrences générées : {$generated}");

        return self::SUCCESS;
    }

    private function generateNext(Task $parent): int
    {
        $today = Carbon::today();
        $horizon = $today->copy()->addWeeks(6);

        $last = Task::on('tenant')
            ->where('recurrence_parent_id', $parent->id)
            ->orderByDesc('due_date')
            ->first() ?? $parent;

        if (! $last->due_date) {
            return 0;
        }

        $nextDue = match ($parent->recurrence_type) {
            'daily' => $last->due_date->copy()->addDays($parent->recurrence_every ?? 1),
            'monthly' => $last->due_date->copy()->addMonths($parent->recurrence_every ?? 1),
            default => $last->due_date->copy()->addWeeks($parent->recurrence_every ?? 1),
        };

        if ($parent->recurrence_ends && $nextDue->gt($parent->recurrence_ends)) {
            return 0;
        }
        if ($nextDue->gt($horizon)) {
            return 0;
        }

        $exists = Task::on('tenant')
            ->where('recurrence_parent_id', $parent->id)
            ->whereDate('due_date', $nextDue)
            ->exists();
        if ($exists) {
            return 0;
        }

        $nextStart = null;
        if ($parent->start_date && $parent->due_date) {
            $nextStart = $nextDue->copy()->subDays($parent->start_date->diffInDays($parent->due_date));
        }

        DB::connection('tenant')->transaction(function () use ($parent, $nextDue, $nextStart) {
            Task::on('tenant')->create([
                'project_id' => $parent->project_id,
                'created_by' => $parent->created_by,
                'assigned_to' => $parent->assigned_to,
                'milestone_id' => $parent->milestone_id,
                'title' => $parent->title,
                'description' => $parent->description,
                'status' => 'todo',
                'priority' => $parent->priority,
                'start_date' => $nextStart,
                'due_date' => $nextDue,
                'estimated_hours' => $parent->estimated_hours,
                'actual_hours' => null,
                'sort_order' => 0,
                'recurrence_type' => null,
                'recurrence_parent_id' => $parent->id,
            ]);
        });

        return 1;
    }
}
