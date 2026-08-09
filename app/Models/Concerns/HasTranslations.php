<?php

namespace App\Models\Concerns;

/**
 * Minimal locale-keyed JSON translations for content models. Translatable
 * attributes are stored as {"en": "...", "ar": "..."} objects (array casts),
 * so the schema stays valid for ANY SUPPORTED_LOCALES configuration — no
 * per-locale columns. Reading falls back to the platform default locale.
 */
trait HasTranslations
{
    public function translate(string $attribute, ?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();

        $values = $this->getAttribute($attribute);

        if (! is_array($values)) {
            return null;
        }

        $value = $values[$locale]
            ?? $values[config('platform.default_locale', 'en')]
            ?? null;

        return is_string($value) ? $value : null;
    }
}
