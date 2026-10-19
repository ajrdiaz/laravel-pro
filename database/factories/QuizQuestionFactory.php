<?php

namespace Database\Factories;

use App\Models\Module;
use App\Models\QuizQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuizQuestion>
 */
class QuizQuestionFactory extends Factory
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
            'question' => fake()->sentence().'?',
            'options' => ['Correct', 'Wrong A', 'Wrong B', 'Wrong C'],
            'correct_index' => 0,
            'explanation' => fake()->sentence(),
        ];
    }
}
