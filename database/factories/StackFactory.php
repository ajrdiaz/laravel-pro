<?php

namespace Database\Factories;

use App\Enums\StackCategory;
use App\Models\Stack;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Stack>
 */
class StackFactory extends Factory
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
            'category' => fake()->randomElement(StackCategory::cases()),
            'name' => fake()->sentence(3),
            'summary' => fake()->sentence(),
            'headline' => ['Laravel', 'Pest'],
            'when_to_use' => [fake()->sentence()],
            'when_to_avoid' => [fake()->sentence()],
            'pros' => [fake()->sentence()],
            'cons' => [fake()->sentence()],
            'also_learn' => [fake()->words(2, true)],
            'sources' => [['title' => 'Laravel', 'url' => 'https://laravel.com/docs']],
            'reviewed_on' => today(),
        ];
    }
}
