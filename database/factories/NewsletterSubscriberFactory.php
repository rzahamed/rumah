<?php

namespace Database\Factories;

use App\Models\NewsletterSubscriber;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NewsletterSubscriber>
 */
class NewsletterSubscriberFactory extends Factory
{
    protected $model = NewsletterSubscriber::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Generated through the same normalizer the endpoint uses, so a
            // factory row can never differ in shape from a real one.
            'email' => NewsletterSubscriber::normalizeEmail($this->faker->unique()->safeEmail()),
            'locale' => (string) config('platform.default_locale', 'en'),
            'consented_at' => now(),
        ];
    }

    /**
     * A subscriber who signed up in a specific locale.
     */
    public function locale(string $locale): static
    {
        return $this->state(fn (): array => ['locale' => $locale]);
    }
}
