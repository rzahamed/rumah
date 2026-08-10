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
    public static function text(string $attribute, string $label, bool $required = true, ?int $maxLength = null): array
    {
        return collect(config('platform.supported_locales', ['en']))
            ->map(fn (string $locale): TextInput => TextInput::make("{$attribute}.{$locale}")
                ->label($label.' ('.strtoupper($locale).')')
                ->required($required && $locale === config('platform.default_locale', 'en'))
                // Existing default preserved exactly; an explicit argument
                // overrides it for bounded repeater content.
                ->maxLength($maxLength ?? 255))
            ->all();
    }

    /**
     * @return list<Textarea>
     */
    public static function textarea(string $attribute, string $label, bool $required = true, int $rows = 6, ?int $maxLength = null): array
    {
        return collect(config('platform.supported_locales', ['en']))
            ->map(fn (string $locale): Textarea => Textarea::make("{$attribute}.{$locale}")
                ->label($label.' ('.strtoupper($locale).')')
                ->required($required && $locale === config('platform.default_locale', 'en'))
                ->rows($rows)
                // 65535 is the rule this helper already applied — kept
                // verbatim unless an explicit bound is supplied.
                ->maxLength($maxLength ?? 65535))
            ->all();
    }

    /**
     * One input per supported locale keyed by the BARE locale ("en", "ar"),
     * for repeater items stored as {"en": ..., "ar": ...}. Same required
     * policy as the other helpers: only the platform default locale is
     * required — other locales are optional translations that fall back to
     * the default at read time.
     *
     * @return list<TextInput>
     */
    public static function flatText(string $label, bool $required = true, ?int $maxLength = null): array
    {
        return collect(config('platform.supported_locales', ['en']))
            ->map(fn (string $locale): TextInput => TextInput::make($locale)
                ->label($label.' ('.strtoupper($locale).')')
                ->required($required && $locale === config('platform.default_locale', 'en'))
                ->maxLength($maxLength ?? 255))
            ->all();
    }
}
