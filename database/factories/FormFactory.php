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

    /**
     * Inquiry-style form exercising both select fields — generic test
     * scaffolding; applications seed their real forms with their own
     * machine values and labels.
     */
    public function inquiry(): static
    {
        return $this->state(fn (array $attributes) => [
            'fields' => [
                [
                    'name' => 'full_name',
                    'type' => 'text',
                    'required' => true,
                    'label' => ['en' => 'Full Name', 'ar' => 'الاسم الكامل'],
                ],
                [
                    'name' => 'email',
                    'type' => 'email',
                    'required' => true,
                    'label' => ['en' => 'Email Address', 'ar' => 'البريد الإلكتروني'],
                ],
                [
                    'name' => 'preferred_contact_method',
                    'type' => 'select',
                    'required' => true,
                    'label' => ['en' => 'Preferred Contact Method', 'ar' => 'طريقة التواصل المفضلة'],
                    'options' => [
                        ['value' => 'phone_call', 'label' => ['en' => 'Phone call', 'ar' => 'مكالمة هاتفية']],
                        ['value' => 'email', 'label' => ['en' => 'Email', 'ar' => 'البريد الإلكتروني']],
                        ['value' => 'whatsapp', 'label' => ['en' => 'WhatsApp', 'ar' => 'واتساب']],
                    ],
                ],
                [
                    'name' => 'topic',
                    'type' => 'select',
                    'required' => true,
                    'label' => ['en' => 'Topic', 'ar' => 'الموضوع'],
                    'options' => [
                        ['value' => 'general', 'label' => ['en' => 'General', 'ar' => 'عام']],
                        ['value' => 'support', 'label' => ['en' => 'Support', 'ar' => 'الدعم']],
                        ['value' => 'partnership', 'label' => ['en' => 'Partnership', 'ar' => 'الشراكات']],
                        ['value' => 'billing', 'label' => ['en' => 'Billing', 'ar' => 'الفوترة']],
                        ['value' => 'other', 'label' => ['en' => 'Other', 'ar' => 'أخرى']],
                    ],
                ],
                [
                    'name' => 'details',
                    'type' => 'textarea',
                    'required' => false,
                    'label' => ['en' => 'Details', 'ar' => 'التفاصيل'],
                ],
            ],
        ]);
    }

    /**
     * Newsletter signup: email plus a REQUIRED consent checkbox enforced
     * server-side with the 'accepted' rule.
     */
    public function newsletter(): static
    {
        return $this->state(fn (array $attributes) => [
            'fields' => [
                [
                    'name' => 'email',
                    'type' => 'email',
                    'required' => true,
                    'label' => ['en' => 'Your email', 'ar' => 'بريدك الإلكتروني'],
                ],
                [
                    'name' => 'consent',
                    'type' => 'checkbox',
                    'required' => true,
                    'label' => [
                        'en' => 'I accept the Privacy Policy and consent to receive curated consulting insights.',
                        'ar' => 'أوافق على سياسة الخصوصية وعلى استلام رسائل استشارية مختارة.',
                    ],
                ],
            ],
        ]);
    }
}
