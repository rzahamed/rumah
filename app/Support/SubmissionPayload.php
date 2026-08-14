<?php

namespace App\Support;

use App\Models\FormSubmission;
use Illuminate\Database\Eloquent\Builder;

/**
 * The ONE place that reads a submission payload semantically.
 *
 * Payload keys are the form definition's machine names, which an
 * administrator can rename or remove. Two rules keep that safe:
 *
 * 1. NAME, INTEREST and MESSAGE are resolved ONLY through the explicit
 *    canonical aliases below. Field TYPE cannot identify them — an ordinary
 *    text or select field is not reliably a person's name or a case
 *    interest — and translated labels are not evidence either. Guessing "the
 *    first text field" would silently mislabel arbitrary answers.
 *
 * 2. EMAIL and PHONE may additionally be resolved through the stored form
 *    definition, because the 'email' and 'tel' field types are unambiguous
 *    about what the value IS.
 *
 * When nothing matches, callers get null and render an em dash. Nothing is
 * inferred, so a renamed key degrades visibly instead of showing the wrong
 * person's data under the wrong heading.
 */
class SubmissionPayload
{
    public const string PLACEHOLDER = '—';

    /** @var list<string> */
    private const array NAME_KEYS = ['name', 'full_name'];

    /** @var list<string> */
    private const array EMAIL_KEYS = ['email'];

    /** @var list<string> */
    private const array PHONE_KEYS = ['phone', 'telephone', 'mobile'];

    /**
     * Deliberately industry-neutral. A project that renames these fields for
     * its own sector adds the new key here; nothing else in the application
     * assumes a particular business.
     *
     * @var list<string>
     */
    private const array INTEREST_KEYS = ['service_type', 'interest'];

    /** @var list<string> */
    private const array INTEREST_DETAIL_KEYS = ['subject'];

    /** @var list<string> */
    private const array MESSAGE_KEYS = ['message', 'details'];

    /**
     * Alias sets exposed for querying, so the Filament resource never needs
     * to know a single payload key itself.
     *
     * @var array<string, list<string>>
     */
    private const array SEARCHABLE = [
        'name' => self::NAME_KEYS,
        'email' => self::EMAIL_KEYS,
        'phone' => self::PHONE_KEYS,
        'interest' => [...self::INTEREST_KEYS, ...self::INTEREST_DETAIL_KEYS],
    ];

    /**
     * @param  string|null  $locale  Explicit locale for translated field and
     *                               option labels. Queued mail must pass the RECIPIENT's locale: the
     *                               worker's app locale is unrelated to who the email is for, and
     *                               mutating it globally inside a job would leak across jobs.
     */
    public function __construct(
        private readonly FormSubmission $submission,
        private readonly ?string $locale = null,
    ) {}

    public static function for(FormSubmission $submission, ?string $locale = null): self
    {
        return new self($submission, $locale);
    }

    private function locale(): string
    {
        return $this->locale ?? app()->getLocale();
    }

    /**
     * Case-insensitive search across one semantic's canonical keys.
     *
     * Reads the JSONB payload with ->> so the comparison happens in
     * PostgreSQL rather than by loading rows into PHP. Unknown semantics
     * match nothing rather than silently searching everything.
     *
     * @param  Builder<FormSubmission>  $query
     * @return Builder<FormSubmission>
     */
    public static function scopeSearch(Builder $query, string $semantic, string $search): Builder
    {
        $keys = self::SEARCHABLE[$semantic] ?? [];

        if ($keys === [] || trim($search) === '') {
            return $query->whereRaw('1 = 0');
        }

        // The term is a bound parameter, so it cannot alter the statement —
        // but LIKE metacharacters inside it would still silently broaden the
        // match, so a search for "50%" must mean a literal "50%". Backslash
        // first, or it would re-escape the escapes.
        $term = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $search);

        return $query->where(function (Builder $inner) use ($keys, $term): void {
            foreach ($keys as $key) {
                $inner->orWhereRaw("payload ->> ? ILIKE ? ESCAPE '\\'", [$key, '%'.$term.'%']);
            }
        });
    }

    public function name(): ?string
    {
        return $this->firstOf(self::NAME_KEYS);
    }

    /** Alias first, then any field the definition declares as type 'email'. */
    public function email(): ?string
    {
        return $this->firstOf(self::EMAIL_KEYS) ?? $this->firstOfType('email');
    }

    /** Alias first, then any field the definition declares as type 'tel'. */
    public function phone(): ?string
    {
        return $this->firstOf(self::PHONE_KEYS) ?? $this->firstOfType('tel');
    }

    /**
     * The service interest, with its subject appended when present —
     * "Service Consultation — Website redesign" reads better than either
     * alone. Option VALUES are machine keys, so the form's own translated
     * option label is used when one exists.
     */
    public function interest(): ?string
    {
        $type = $this->firstOf(self::INTEREST_KEYS);

        if ($type === null) {
            return null;
        }

        $label = $this->optionLabelFor(self::INTEREST_KEYS, $type) ?? $type;
        $detail = $this->firstOf(self::INTEREST_DETAIL_KEYS);

        return $detail === null ? $label : $label.' — '.$detail;
    }

    public function message(): ?string
    {
        return $this->firstOf(self::MESSAGE_KEYS);
    }

    /**
     * Every submitted field in DEFINITION order, so the view page can show
     * everything that was collected — including answers that map to no
     * summary column. Values are returned raw for the caller to escape.
     *
     * @return list<array{key: string, label: string, value: string}>
     */
    public function all(): array
    {
        $payload = $this->payload();
        $rows = [];

        foreach ($this->fields() as $field) {
            $key = is_array($field) ? ($field['name'] ?? null) : null;

            if (! is_string($key) || ! array_key_exists($key, $payload)) {
                continue;
            }

            $rows[] = [
                'key' => $key,
                'label' => $this->labelFor($field, $key),
                'value' => $this->stringify($payload[$key], $key),
            ];
        }

        // Anything stored under a key the definition no longer declares —
        // a renamed field, say — is still shown rather than quietly lost.
        foreach ($payload as $key => $value) {
            if (! is_string($key) || $this->declares($key)) {
                continue;
            }

            $rows[] = [
                'key' => $key,
                'label' => $key,
                'value' => $this->stringify($value, $key),
            ];
        }

        return $rows;
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        $payload = $this->submission->payload;

        return is_array($payload) ? $payload : [];
    }

    /** @return list<mixed> */
    private function fields(): array
    {
        $fields = $this->submission->form?->fields;

        return is_array($fields) ? $fields : [];
    }

    private function declares(string $key): bool
    {
        foreach ($this->fields() as $field) {
            if (is_array($field) && ($field['name'] ?? null) === $key) {
                return true;
            }
        }

        return false;
    }

    /**
     * First non-blank scalar among the given canonical keys.
     *
     * @param  list<string>  $keys
     */
    private function firstOf(array $keys): ?string
    {
        $payload = $this->payload();

        foreach ($keys as $key) {
            $value = $payload[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    /** First submitted value whose DEFINITION field type matches. */
    private function firstOfType(string $type): ?string
    {
        $payload = $this->payload();

        foreach ($this->fields() as $field) {
            if (! is_array($field) || ($field['type'] ?? null) !== $type) {
                continue;
            }

            $key = $field['name'] ?? null;
            $value = is_string($key) ? ($payload[$key] ?? null) : null;

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    /**
     * Translated option label for a select value, via the form's own
     * definition. Falls back to null so the caller can show the raw value.
     *
     * @param  list<string>  $keys
     */
    private function optionLabelFor(array $keys, string $value): ?string
    {
        $form = $this->submission->form;

        if ($form === null) {
            return null;
        }

        foreach ($keys as $key) {
            $label = $form->optionLabel($key, $value, $this->locale());

            if (is_string($label) && $label !== '') {
                return $label;
            }
        }

        return null;
    }

    /** @param  array<string, mixed>  $field */
    private function labelFor(array $field, string $key): string
    {
        $label = $field['label'] ?? null;

        if (is_array($label)) {
            $locale = $this->locale();
            $resolved = $label[$locale] ?? $label[config('platform.default_locale', 'en')] ?? null;

            if (is_string($resolved) && trim($resolved) !== '') {
                return $resolved;
            }
        }

        return $key;
    }

    /** Render any stored value as display text; booleans become yes/no. */
    private function stringify(mixed $value, string $key): string
    {
        if (is_bool($value)) {
            return $value ? __('content.submissions.yes') : __('content.submissions.no');
        }

        if (is_array($value)) {
            return implode(', ', array_map(
                fn (mixed $item): string => is_scalar($item) ? (string) $item : '',
                $value,
            ));
        }

        if (! is_scalar($value) || trim((string) $value) === '') {
            return self::PLACEHOLDER;
        }

        $string = trim((string) $value);

        return $this->optionLabelFor([$key], $string) ?? $string;
    }
}
