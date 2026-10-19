<?php

namespace Database\Factories;

use App\Models\Lesson;
use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lesson>
 */
class LessonFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'module_id' => Module::factory(),
            'slug' => fake()->unique()->slug(3),
            'position' => fake()->unique()->numberBetween(1, 5000),
            'title' => fake()->sentence(4),
            'summary' => fake()->sentence(),
            'minutes' => fake()->numberBetween(3, 15),
            'body' => '## '.fake()->sentence(3)."\n\n".fake()->paragraph(),
        ];
    }
}
