<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\Project;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'created_by' => User::factory(),
            'name' => $this->faker->sentence(4, false),
            'description' => $this->faker->paragraph(),
            'status' => 'active',
            'start_date' => now()->subDays(rand(0, 90)),
            'due_date' => now()->addDays(rand(30, 365)),
            'color' => $this->faker->randomElement([
                '#1E3A5F', '#16A34A', '#EA580C', '#8B5CF6', '#3B82F6',
            ]),
        ];
    }

    public function active(): static
    {
        return $this->state(['status' => 'active']);
    }

    public function completed(): static
    {
        return $this->state(['status' => 'completed']);
    }

    public function archived(): static
    {
        return $this->state(['status' => 'archived']);
    }
}
