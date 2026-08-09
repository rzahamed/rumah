<?php

namespace Database\Factories;

use App\Models\TeamMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeamMember>
 */
class TeamMemberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => [
                'en' => fake()->name(),
                'ar' => 'عضو الفريق',
            ],
            'position' => [
                'en' => fake()->jobTitle(),
                'ar' => 'المسمى الوظيفي',
            ],
            'bio' => [
                'en' => fake()->sentence(12),
                'ar' => fake()->sentence(12),
            ],
            'photo_path' => null,
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
