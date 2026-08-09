<?php

namespace Database\Factories;

use App\Models\Form;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Form>
 */
class FormFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true).' form';

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'is_active' => true,
            'fields' => [
                [
                    'name' => 'name',
                    'type' => 'text',
                    'required' => true,
                    'label' => ['en' => 'Name', 'ar' => 'الاسم'],
                ],
                [
                    'name' => 'email',
                    'type' => 'email',
                    'required' => true,
                    'label' => ['en' => 'Email', 'ar' => 'البريد الإلكتروني'],
                ],
                [
                    'name' => 'message',
                    'type' => 'textarea',
                    'required' => true,
                    'label' => ['en' => 'Message', 'ar' => 'الرسالة'],
                ],
            ],
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
