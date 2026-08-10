<?php

namespace Database\Factories;

use App\Models\Faq;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Faq>
 */
class FaqFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'question' => [
                'en' => rtrim(fake()->unique()->sentence(8), '.').'?',
                'ar' => 'سؤال شائع؟',
            ],
            'answer' => [
                'en' => fake()->paragraph(),
                'ar' => fake()->paragraph(),
            ],
            'sort_order' => 0,
            'is_visible' => true,
        ];
    }

    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_visible' => false,
        ]);
    }
}
