<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\MediaAlbum;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MediaAlbumFactory extends Factory
{
    protected $model = MediaAlbum::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'visibility' => 'public',
            'created_by' => User::factory(),
        ];
    }

    public function public(): static
    {
        return $this->state(['visibility' => 'public']);
    }

    public function restricted(): static
    {
        return $this->state(['visibility' => 'restricted']);
    }

    public function private(): static
    {
        return $this->state(['visibility' => 'private']);
    }
}
