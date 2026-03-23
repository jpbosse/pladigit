<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\Project;
use App\Models\Tenant\Task;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        $start = now()->subDays(rand(0, 30));

        return [
            'project_id' => Project::factory(),
            'created_by' => User::factory(),
            'assigned_to' => null,
            'parent_task_id' => null,
            'milestone_id' => null,
            'title' => $this->faker->sentence(5, false),
            'description' => $this->faker->optional()->paragraph(),
            'status' => 'todo',
            'priority' => $this->faker->randomElement(['low', 'medium', 'high', 'urgent']),
            'start_date' => $start,
            'due_date' => (clone $start)->addDays(rand(3, 21)),
            'estimated_hours' => $this->faker->optional()->numberBetween(1, 40),
            'actual_hours' => null,
            'sort_order' => 0,
        ];
    }

    public function todo(): static
    {
        return $this->state(['status' => 'todo']);
    }

    public function inProgress(): static
    {
        return $this->state(['status' => 'in_progress']);
    }

    public function done(): static
    {
        return $this->state([
            'status' => 'done',
            'actual_hours' => $this->faker->numberBetween(1, 40),
        ]);
    }

    public function urgent(): static
    {
        return $this->state(['priority' => 'urgent']);
    }

    public function forGantt(): static
    {
        $start = now()->subDays(rand(0, 14));

        return $this->state([
            'start_date' => $start,
            'due_date' => (clone $start)->addDays(rand(5, 30)),
        ]);
    }
}
