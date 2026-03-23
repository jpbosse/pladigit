<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\Project;
use App\Models\Tenant\ProjectMilestone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectMilestone>
 */
class ProjectMilestoneFactory extends Factory
{
    protected $model = ProjectMilestone::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'title' => 'Jalon — '.$this->faker->sentence(3, false),
            'description' => $this->faker->optional()->sentence(),
            'due_date' => now()->addDays(rand(14, 180)),
            'reached_at' => null,
            'color' => '#EA580C',
        ];
    }

    public function reached(): static
    {
        return $this->state([
            'reached_at' => now()->subDays(rand(1, 30)),
            'due_date' => now()->subDays(rand(31, 90)),
        ]);
    }

    public function late(): static
    {
        return $this->state([
            'reached_at' => null,
            'due_date' => now()->subDays(rand(1, 30)),
        ]);
    }
}
