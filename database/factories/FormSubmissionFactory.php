<?php

namespace Database\Factories;

use App\Models\Form;
use App\Models\FormSubmission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormSubmission>
 */
class FormSubmissionFactory extends Factory
{
    /**
     * Define the model's default state. The payload matches FormFactory's
     * default contact-style field definition.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'form_id' => Form::factory(),
            'payload' => [
                'name' => fake()->name(),
                'email' => fake()->safeEmail(),
                'message' => fake()->sentence(12),
            ],
        ];
    }
}
