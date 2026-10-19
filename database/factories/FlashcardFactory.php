<?php

namespace Database\Factories;

use App\Models\Flashcard;
use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Flashcard>
 */
class FlashcardFactory extends Factory
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
            'answer' => fake()->sentence(),
        ];
    }
}
