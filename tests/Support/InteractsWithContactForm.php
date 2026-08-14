<?php

namespace Tests\Support;

use App\Models\Form;

/**
 * Setup helpers for the ONE canonical Contact form.
 *
 * The product exposes exactly one Contact form. It is provisioned into
 * every fresh database — including the test database, because
 * RefreshDatabase runs the same migrations — so a test must CONFIGURE that
 * row rather than insert another one with the same slug. A second
 * `contact` insert violates the unique slug constraint protecting the
 * canonical key, making it a setup bug rather than a valid fixture.
 *
 * Client-specific by design, so it lives beside the suite rather than in
 * the starter-derived AdminTestCase.
 */
trait InteractsWithContactForm
{
    /**
     * The canonical Contact row, optionally reconfigured.
     *
     * `sole()` deliberately fails if the canonical `contact` row is missing
     * or duplicated, instead of silently using the wrong fixture.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function canonicalContactForm(array $attributes = []): Form
    {
        $form = Form::query()->where('slug', 'contact')->sole();

        if ($attributes !== []) {
            $form->update($attributes);
        }

        return $form;
    }

    /**
     * The canonical Contact row carrying the factory's generic
     * {name, email, message} definition.
     *
     * The shipped board-derived definition is deliberately NOT used by the
     * pipeline suites: they exercise submission, validation and Turnstile
     * behaviour, not the client's copy, and must not start failing because
     * a field was added or renamed on the design board.
     */
    protected function genericContactForm(): Form
    {
        return $this->canonicalContactForm([
            'is_active' => true,
            'fields' => Form::factory()->make()->fields,
        ]);
    }
}
