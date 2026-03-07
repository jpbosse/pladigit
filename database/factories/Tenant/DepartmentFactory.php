<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'type' => 'direction',
            'parent_id' => null,
            'created_by' => null,
        ];
    }

    /**
     * Direction (sans parent).
     */
    public function direction(): static
    {
        return $this->state([
            'type' => 'direction',
            'parent_id' => null,
        ]);
    }

    /**
     * Service rattaché à une direction.
     */
    public function service(int $parentId): static
    {
        return $this->state([
            'type' => 'service',
            'parent_id' => $parentId,
        ]);
    }
}
