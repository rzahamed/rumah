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

    public function withDetails(): static
    {
        return $this->state(fn (array $attributes) => [
            'email' => fake()->unique()->safeEmail(),
            'highlights' => [
                ['en' => 'Strategic Planning', 'ar' => 'التخطيط الاستراتيجي'],
                ['en' => 'Team Leadership', 'ar' => 'قيادة الفرق'],
            ],
            'credentials' => [
                [
                    'title' => ['en' => "Bachelor's Degree", 'ar' => 'درجة البكالوريوس'],
                    'institution' => ['en' => 'Example University', 'ar' => 'جامعة المثال'],
                    'description' => ['en' => 'Strong academic foundation in the field.', 'ar' => 'أساس أكاديمي متين في المجال.'],
                ],
            ],
            'expertise' => [
                ['en' => 'Project Management', 'ar' => 'إدارة المشاريع'],
                ['en' => 'Data Analysis', 'ar' => 'تحليل البيانات'],
            ],
            'licence_image_path' => null,
        ]);
    }
}
