<?php

namespace Database\Factories;

use App\Enums\ProjectLevel;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(2),
            'position' => fake()->unique()->numberBetween(1, 5000),
            'level' => fake()->randomElement(ProjectLevel::cases()),
            'title' => fake()->sentence(4),
            'summary' => fake()->sentence(),
            'hours' => fake()->numberBetween(4, 30),
            'stack_id' => null,
            'deliverables' => [fake()->sentence()],
            'stretch' => [fake()->sentence()],
        ];
    }
}
