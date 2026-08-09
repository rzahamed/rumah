<?php

namespace App\Filament\Support;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

/**
 * Builds one input per supported locale for locale-keyed JSON attributes
 * ("title.en", "title.ar", …) so every content resource renders whatever
 * SUPPORTED_LOCALES is configured — nothing locale-specific is hardcoded.
 * The platform default locale is the required one; other locales are
 * optional translations.
 */
class TranslatableInputs
{
    /**
     * @return list<TextInput>
     */
    public static function text(string $attribute, string $label, bool $required = true): array
    {
        return collect(config('platform.supported_locales', ['en']))
            ->map(fn (string $locale): TextInput => TextInput::make("{$attribute}.{$locale}")
                ->label($label.' ('.strtoupper($locale).')')
                ->required($required && $locale === config('platform.default_locale', 'en'))
                ->maxLength(255))
            ->all();
    }

    /**
     * @return list<Textarea>
     */
    public static function textarea(string $attribute, string $label, bool $required = true, int $rows = 6): array
    {
        return collect(config('platform.supported_locales', ['en']))
            ->map(fn (string $locale): Textarea => Textarea::make("{$attribute}.{$locale}")
                ->label($label.' ('.strtoupper($locale).')')
                ->required($required && $locale === config('platform.default_locale', 'en'))
                ->rows($rows)
                ->maxLength(65535))
            ->all();
    }
}
