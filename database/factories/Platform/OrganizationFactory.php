<?php

namespace Database\Factories\Platform;

use App\Models\Platform\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        $name = $this->faker->city();
        $slug = Str::slug($name).'-'.$this->faker->unique()->randomNumber(4);

        return [
            'name' => 'Commune de '.$name,
            'slug' => $slug,
            'db_name' => 'pladigit_'.$slug,
            'status' => 'active',
            'plan' => 'communautaire',
            'max_users' => 9999,
            'primary_color' => '#1E3A5F',
            'timezone' => 'Europe/Paris',
            'locale' => 'fr_FR',
        ];
    }

    public function suspended(): static
    {
        return $this->state(['status' => 'suspended']);
    }

    public function assistance(): static
    {
        return $this->state(['plan' => 'assistance', 'max_users' => 200]);
    }
}
