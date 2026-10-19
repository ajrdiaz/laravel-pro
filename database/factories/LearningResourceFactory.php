<?php

namespace Database\Factories;

use App\Enums\ResourceKind;
use App\Enums\ResourceLanguage;
use App\Models\LearningResource;
use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearningResource>
 */
class LearningResourceFactory extends Factory
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
            'title' => fake()->sentence(4),
            'url' => 'https://example.com/'.fake()->slug(2),
            'provider' => fake()->company(),
            'kind' => ResourceKind::Docs,
            'language' => ResourceLanguage::En,
            'is_free' => true,
            'note' => fake()->sentence(),
        ];
    }

    public function video(string $url = 'https://www.youtube.com/watch?v=gv9z1yv0_7k'): static
    {
        return $this->state(['kind' => ResourceKind::Video, 'url' => $url]);
    }
}
