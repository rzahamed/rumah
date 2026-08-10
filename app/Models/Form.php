<?php

namespace App\Models;

use Database\Factories\FormFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\Rule;

/**
 * An admin-defined public form: a slug, an active switch, and an ordered
 * list of field definitions ({name, type, required, label:{locale: ...}}).
 * Validation rules for public submissions are derived from the definition
 * here so the admin form and the public endpoint can never disagree.
 */
#[Fillable(['name', 'slug', 'is_active', 'fields'])]
class Form extends Model
{
    /** @use HasFactory<FormFactory> */
    use HasFactory;

    /**
     * The only accepted field types; the admin form builder offers exactly
     * this list.
     *
     * @var list<string>
     */
    public const array FIELD_TYPES = ['text', 'textarea', 'email', 'tel', 'number', 'select', 'checkbox'];

    /**
     * Machine-identifier pattern for select option values — shared by the
     * admin builder rule and the model's read-time sanitizer so they can
     * never disagree. Values are stable identifiers, not display text.
     */
    public const string OPTION_VALUE_PATTERN = '/^[a-z][a-z0-9_-]{0,63}$/';

    public const int MAX_SELECT_OPTIONS = 20;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'fields' => 'array',
        ];
    }

    /**
     * @return HasMany<FormSubmission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }

    /**
     * @return list<string>
     */
    public function fieldNames(): array
    {
        return collect($this->fields ?? [])
            ->pluck('name')
            ->filter(fn ($name): bool => is_string($name) && $name !== '')
            ->values()
            ->all();
    }

    /**
     * Laravel validation rules derived from the stored definition. Unknown
     * types (possible only through data tampering, never the admin UI) are
     * validated as bounded plain text rather than accepted blindly.
     *
     * @return array<string, list<mixed>>
     */
    public function validationRules(): array
    {
        $rules = [];

        foreach ($this->fields ?? [] as $field) {
            $name = $field['name'] ?? null;

            if (! is_string($name) || $name === '') {
                continue;
            }

            $required = (bool) ($field['required'] ?? false);
            $type = $field['type'] ?? 'text';

            // Checkbox presence semantics differ from every other type:
            // required means "must be accepted" (consent — the server is
            // the authority, never client JS), optional means "boolean
            // when sent".
            if ($type === 'checkbox') {
                $rules[$name] = $required ? ['accepted'] : ['nullable', 'boolean'];

                continue;
            }

            $typeRules = match ($type) {
                'text' => ['string', 'max:255'],
                'textarea' => ['string', 'max:5000'],
                'email' => ['string', 'email', 'max:255'],
                'tel' => ['string', 'max:50'],
                'number' => ['numeric'],
                // The only stored data that ever reaches the validator is
                // this sanitized allowlist, escaped by Rule::in — rules
                // themselves always come from this fixed match.
                'select' => ['string', Rule::in($this->allowedOptionValues($field))],
                default => ['string', 'max:255'],
            };

            $rules[$name] = [
                $required ? 'required' : 'nullable',
                ...$typeRules,
            ];
        }

        return $rules;
    }

    /**
     * Read-time sanitizer for a select field's stored options. The stored
     * definition is authoritative — a direct database change can introduce
     * a new syntactically valid option — so the guarantee here is exactly:
     * malformed values are discarded, duplicates are removed, the list is
     * capped at MAX_SELECT_OPTIONS, and no stored value can inject
     * executable validation rules (values only ever reach Rule::in, which
     * escapes them; the rules themselves come from a fixed match). A select
     * with no valid options rejects every submitted value.
     *
     * @param  array<string, mixed>  $field
     * @return list<string>
     */
    private function allowedOptionValues(array $field): array
    {
        return collect(is_array($field['options'] ?? null) ? $field['options'] : [])
            ->map(fn ($option) => is_array($option) ? ($option['value'] ?? null) : null)
            ->filter(fn ($value): bool => is_string($value)
                && preg_match(self::OPTION_VALUE_PATTERN, $value) === 1)
            ->unique()
            ->take(self::MAX_SELECT_OPTIONS)
            ->values()
            ->all();
    }

    /**
     * Declared checkbox field names — the public controller normalizes
     * their submitted values to real booleans.
     *
     * @return list<string>
     */
    public function checkboxFieldNames(): array
    {
        return collect($this->fields ?? [])
            ->filter(fn ($field): bool => is_array($field)
                && ($field['type'] ?? null) === 'checkbox'
                && is_string($field['name'] ?? null)
                && $field['name'] !== '')
            ->pluck('name')
            ->values()
            ->all();
    }

    /**
     * Sanitized options of a select field with locale-resolved labels:
     * current locale, then the platform default, then the machine value
     * itself — the public form never renders an unlabeled choice.
     *
     * @return list<array{value: string, label: string}>
     */
    public function selectOptions(string $fieldName, ?string $locale = null): array
    {
        $locale ??= app()->getLocale();
        $default = (string) config('platform.default_locale', 'en');

        $field = collect($this->fields ?? [])
            ->first(fn ($field): bool => is_array($field) && ($field['name'] ?? null) === $fieldName);

        // Only a genuine select field exposes choices: a malformed or
        // tampered non-select field carrying a stale options key must
        // never leak options through this public helper.
        if (! is_array($field) || ($field['type'] ?? null) !== 'select') {
            return [];
        }

        $allowed = $this->allowedOptionValues($field);

        return collect(is_array($field['options'] ?? null) ? $field['options'] : [])
            ->filter(fn ($option): bool => is_array($option)
                && in_array($option['value'] ?? null, $allowed, true))
            ->unique(fn (array $option) => $option['value'])
            ->map(function (array $option) use ($locale, $default): array {
                $labels = is_array($option['label'] ?? null) ? $option['label'] : [];

                $label = $labels[$locale] ?? $labels[$default] ?? null;

                return [
                    'value' => (string) $option['value'],
                    'label' => is_string($label) && trim($label) !== '' ? $label : (string) $option['value'],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Locale-resolved label for one machine value, or null when the value
     * is not part of the field's sanitized allowlist.
     */
    public function optionLabel(string $fieldName, string $value, ?string $locale = null): ?string
    {
        foreach ($this->selectOptions($fieldName, $locale) as $option) {
            if ($option['value'] === $value) {
                return $option['label'];
            }
        }

        return null;
    }
}
