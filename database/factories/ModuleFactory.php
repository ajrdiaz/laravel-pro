<?php

namespace Database\Factories;

use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Module>
 */
class ModuleFactory extends Factory
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
            'section' => 'Fundamentos',
            'command' => 'php artisan make:'.fake()->word(),
            'title' => fake()->sentence(3),
        ];
    }
}
