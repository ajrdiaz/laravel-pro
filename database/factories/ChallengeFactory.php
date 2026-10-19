<?php

namespace Database\Factories;

use App\Models\Challenge;
use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Challenge>
 */
class ChallengeFactory extends Factory
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
            'position' => fake()->unique()->numberBetween(1, 5000),
            'body' => fake()->paragraph(),
        ];
    }
}
