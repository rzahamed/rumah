{{--
    Paper: Contact Page → "Card / Consultation Form" → "Form / Consultation
    Request". The white panel (radius 20, padding 57px 49px, gap 20) inside
    the midnight-blue card.

    THE FIELDS ARE THE CMS'S. This renders the ONE canonical admin-defined
    Contact form — the same active row PublicFormController serves — field by
    field from its stored definition, and posts to the EXISTING submission
    endpoint (public.forms.submit / .localized) with the CSRF token and the
    Turnstile widget for ACTION_PUBLIC_FORM. Validation rules come from that
    definition on the server; the HTML `required` attributes are a convenience
    only. Errors return in the default bag and are shown beside their fields;
    success arrives as the 'status' flash. No field, option or label is
    authored here — Paper's "Preferred Contact Method" field and its
    "Consultation Type" choices exist only once an administrator adds them to
    the form definition, and then render here without code changes.

    Paper's anatomy by field type:
      text/email/tel/number  — a 52px shell (1px #756A6A4D border, radius 12,
                               0 2px 6 #0000000F), 21px inline padding, the
                               20px field glyph 14px before the 16px/20px
                               placeholder text; two such fields share a row.
      select                 — a 16px/20px label over wrapping choice chips
                               11px apart (radius 8, same border/elevation,
                               12px padding, placeholder-coloured text). Paper
                               draws every chip unselected; the CHECKED chip
                               here takes the brand surface — a necessary
                               state Paper doesn't specify (flagged).
      textarea               — a 239px shell, 28px inline / 17px block
                               padding, glyph and text top-aligned, 15px apart.
      checkbox               — not drawn in Paper; a plain native checkbox
                               with its label.
    Consecutive short fields pair up two per row; select, textarea and
    checkbox each take a full row, and a short field left alone in a row
    grows to the full width — which is how the shipped definition lays out.
    Labels are visually the placeholders, exactly as Paper draws them, and are
    also real <label>s for assistive technology.
--}}
@props(['form'])

@php
    $locale = app()->getLocale();
    $default = (string) config('platform.default_locale', 'en');

    // The two routing variants are separate routes, exactly as elsewhere.
    $action = $locale === $default
        ? route('public.forms.submit', ['slug' => $form->slug])
        : route('public.forms.submit.localized', ['locale' => $locale, 'slug' => $form->slug]);

    $shortTypes = ['text', 'email', 'tel', 'number'];
    $rows = [];
    $pending = [];

    foreach ($form->fields ?? [] as $field) {
        if (! is_array($field) || ! is_string($field['name'] ?? null) || $field['name'] === '') {
            continue;
        }

        $type = in_array($field['type'] ?? null, \App\Models\Form::FIELD_TYPES, true) ? $field['type'] : 'text';
        $labels = is_array($field['label'] ?? null) ? $field['label'] : [];
        $label = $labels[$locale] ?? $labels[$default] ?? null;

        $field['type'] = $type;
        $field['label_text'] = is_string($label) && trim($label) !== '' ? $label : $field['name'];
        $field['required'] = (bool) ($field['required'] ?? false);
        $field['id'] = 'contact-'.$field['name'];

        if (in_array($type, $shortTypes, true)) {
            $pending[] = $field;

            if (count($pending) === 2) {
                $rows[] = ['kind' => 'pair', 'fields' => $pending];
                $pending = [];
            }

            continue;
        }

        if ($pending !== []) {
            $rows[] = ['kind' => 'pair', 'fields' => $pending];
            $pending = [];
        }

        $rows[] = ['kind' => $type, 'field' => $field];
    }

    if ($pending !== []) {
        $rows[] = ['kind' => 'pair', 'fields' => $pending];
    }

    $inputTypeOf = fn (string $type): string => match ($type) {
        'email' => 'email',
        'tel' => 'tel',
        'number' => 'number',
        default => 'text',
    };

    $autocompleteOf = fn (array $field): ?string => match (true) {
        in_array($field['name'], ['name', 'full_name'], true) => 'name',
        $field['type'] === 'email' => 'email',
        $field['type'] === 'tel' => 'tel',
        default => null,
    };
@endphp

<form method="POST" action="{{ $action }}" class="flex w-full flex-col gap-[20px] rounded-card bg-white px-lg py-xl tablet:px-[49px] tablet:py-[57px]">
    @csrf

    @if (session('status'))
        <p class="t-ui text-text-primary" role="status">{{ session('status') }}</p>
    @endif

    @if ($errors->any())
        <p class="form-field__error t-caption" role="alert">{{ __('contact.form.error_summary') }}</p>
    @endif

    @foreach ($rows as $row)
        @if ($row['kind'] === 'pair')
            <div class="flex w-full flex-col gap-[20px] tablet:flex-row tablet:gap-md">
                @foreach ($row['fields'] as $field)
                    <div class="flex min-w-0 grow basis-0 flex-col gap-xs">
                        <label class="sr-only" for="{{ $field['id'] }}">{{ $field['label_text'] }}</label>
                        <div class="field-shell flex h-[52px] w-full items-center gap-[14px] rounded-sm border border-border-field bg-white px-[21px] text-text-field-placeholder shadow-faint">
                            <x-site.icon.field class="size-5" />
                            <input
                                class="t-ui w-full min-w-0 bg-transparent text-text-primary placeholder:text-text-field-placeholder"
                                id="{{ $field['id'] }}"
                                name="{{ $field['name'] }}"
                                type="{{ $inputTypeOf($field['type']) }}"
                                placeholder="{{ $field['label_text'] }}"
                                value="{{ old($field['name']) }}"
                                @if ($autocompleteOf($field) !== null) autocomplete="{{ $autocompleteOf($field) }}" @endif
                                @required($field['required'])
                                @if ($errors->has($field['name'])) aria-invalid="true" aria-describedby="{{ $field['id'] }}-error" @endif
                            >
                        </div>
                        @error($field['name'])
                            <p class="form-field__error t-caption" id="{{ $field['id'] }}-error">{{ $message }}</p>
                        @enderror
                    </div>
                @endforeach
            </div>
        @elseif ($row['kind'] === 'select')
            @php($field = $row['field'])
            <fieldset class="flex w-full flex-col pt-[10px]" @if ($errors->has($field['name'])) aria-describedby="{{ $field['id'] }}-error" @endif>
                <legend class="t-ui text-text-primary">{{ $field['label_text'] }}</legend>
                <div class="mt-[11px] flex flex-wrap gap-[11px]">
                    @foreach ($form->selectOptions($field['name']) as $option)
                        <label class="relative cursor-pointer">
                            <input
                                class="peer sr-only"
                                type="radio"
                                name="{{ $field['name'] }}"
                                value="{{ $option['value'] }}"
                                @checked(old($field['name']) === $option['value'])
                                @required($field['required'])
                            >
                            <span class="t-ui flex items-center rounded-chip border border-border-field bg-white p-sm text-text-field-placeholder shadow-faint transition-colors peer-checked:border-background-brand peer-checked:bg-background-brand peer-checked:text-text-on-brand peer-focus-visible:shadow-[0_0_0_2px_var(--color-white),0_0_0_5px_var(--color-obsidian)]">{{ $option['label'] }}</span>
                        </label>
                    @endforeach
                </div>
                @error($field['name'])
                    <p class="form-field__error t-caption mt-xs" id="{{ $field['id'] }}-error">{{ $message }}</p>
                @enderror
            </fieldset>
        @elseif ($row['kind'] === 'textarea')
            @php($field = $row['field'])
            <div class="flex w-full flex-col gap-xs">
                <label class="sr-only" for="{{ $field['id'] }}">{{ $field['label_text'] }}</label>
                <div class="field-shell flex min-h-[239px] w-full items-start gap-[15px] rounded-sm border border-border-field bg-white px-[28px] py-[17px] text-text-field-placeholder shadow-faint">
                    <x-site.icon.field class="size-5" />
                    <textarea
                        class="t-ui min-h-[205px] w-full min-w-0 grow resize-y bg-transparent text-text-primary placeholder:text-text-field-placeholder"
                        id="{{ $field['id'] }}"
                        name="{{ $field['name'] }}"
                        placeholder="{{ $field['label_text'] }}"
                        @required($field['required'])
                        @if ($errors->has($field['name'])) aria-invalid="true" aria-describedby="{{ $field['id'] }}-error" @endif
                    >{{ old($field['name']) }}</textarea>
                </div>
                @error($field['name'])
                    <p class="form-field__error t-caption" id="{{ $field['id'] }}-error">{{ $message }}</p>
                @enderror
            </div>
        @elseif ($row['kind'] === 'checkbox')
            @php($field = $row['field'])
            <div class="flex w-full flex-col gap-xs">
                <label class="flex items-start gap-sm" for="{{ $field['id'] }}">
                    <input
                        class="mt-[2px] size-4 shrink-0"
                        id="{{ $field['id'] }}"
                        name="{{ $field['name'] }}"
                        type="checkbox"
                        value="1"
                        @checked(old($field['name']))
                        @required($field['required'])
                        @if ($errors->has($field['name'])) aria-invalid="true" aria-describedby="{{ $field['id'] }}-error" @endif
                    >
                    <span class="t-ui text-text-primary">{{ $field['label_text'] }}</span>
                </label>
                @error($field['name'])
                    <p class="form-field__error t-caption" id="{{ $field['id'] }}-error">{{ $message }}</p>
                @enderror
            </div>
        @endif
    @endforeach

    <x-public.turnstile :action="\App\Support\Turnstile::ACTION_PUBLIC_FORM" />

    <button class="t-ui flex h-[52px] w-full items-center justify-center rounded-sm border border-border-field bg-background-brand text-text-on-brand shadow-faint transition-colors hover:bg-teal-glow" type="submit">{{ __('contact.form.submit') }}</button>
</form>
