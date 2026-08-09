<?php

namespace App\Models;

use Database\Factories\FormFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
    public const array FIELD_TYPES = ['text', 'textarea', 'email', 'tel', 'number'];

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

            $typeRules = match ($field['type'] ?? 'text') {
                'text' => ['string', 'max:255'],
                'textarea' => ['string', 'max:5000'],
                'email' => ['string', 'email', 'max:255'],
                'tel' => ['string', 'max:50'],
                'number' => ['numeric'],
                default => ['string', 'max:255'],
            };

            $rules[$name] = [
                ($field['required'] ?? false) ? 'required' : 'nullable',
                ...$typeRules,
            ];
        }

        return $rules;
    }
}
