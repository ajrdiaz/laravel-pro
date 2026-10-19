<?php

namespace Database\Factories;

use App\Models\JobAnalysis;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobAnalysis>
 */
class JobAnalysisFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->jobTitle(),
            'body' => 'Buscamos una persona con experiencia en Laravel, Vue y MySQL para construir APIs REST.',
        ];
    }
}
