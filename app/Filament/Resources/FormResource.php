<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FormResource\Pages\EditForm;
use App\Filament\Resources\FormResource\Pages\ListForms;
use App\Models\Form;
use BackedEnum;
use Closure;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * The ONE canonical Contact form, presented as a fixed single-record
 * module: list + edit ONLY.
 *
 * The generic submission engine underneath is unchanged — it still supports
 * arbitrary slugs, derives validation from the stored definition and accepts
 * POST /forms/{slug} exactly as before. What is fixed is the PRODUCT
 * surface: administrators configure the Contact form's fields, they do not
 * invent new public forms. So the canonical row is provisioned by migration
 * and this resource offers no create and no delete route.
 *
 * getEloquentQuery() scopes the whole resource to the canonical slug. In
 * Filament v5 the same query backs record ROUTE BINDING
 * (Resource\Concerns\HasRoutes::getRecordRouteBindingEloquentQuery), so a
 * hand-typed edit URL for any other form resolves to nothing and 404s
 * rather than opening a record this module does not own.
 *
 * Restrictions are enforced HERE, not only in FormPolicy, because active
 * super admins bypass policies via Gate::before.
 *
 * The slug is displayed but never writable: config('platform.contact_form_slug')
 * resolves the public /contact panel by that exact value, so an edited slug
 * would silently empty the page.
 */
class FormResource extends Resource
{
    protected static ?string $model = Form::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?int $navigationSort = 1;

    /**
     * Machine-key pattern for a field name. Shared by the admin rule and the
     * normalizer so the two can never disagree.
     */
    public const string FIELD_NAME_PATTERN = '/^[a-z][a-z0-9_]*$/';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('nav.groups.forms');
    }

    public static function getModelLabel(): string
    {
        return __('content.forms.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('content.forms.plural');
    }

    /**
     * Scope the entire resource — list, edit and route binding — to the
     * canonical Contact row. Any other form the engine may hold is invisible
     * and unreachable through this module.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('slug', (string) config('platform.contact_form_slug', 'contact'));
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('content.fields.name'))
                    ->required()
                    ->maxLength(255),

                // Read-only for orientation: the public Contact panel is
                // looked up by this exact value.
                TextInput::make('slug')
                    ->label(__('content.fields.slug'))
                    ->disabled()
                    ->dehydrated(false),

                Toggle::make('is_active')
                    ->label(__('content.fields.is_active'))
                    ->default(true),

                Repeater::make('fields')
                    ->label(__('content.fields.form_fields'))
                    ->required()
                    ->minItems(1)
                    // Bound the definition size: 20 fields is generous for
                    // any public form and keeps stored definitions small.
                    ->maxItems(20)
                    // Authoritative duplicate guard. distinct() below sees
                    // only what the browser submitted, so two keys that
                    // differ before normalization ("Full Name" and
                    // "full_name") would pass it and then collide once
                    // normalized — silently overwriting one field's rules
                    // and payload key. This runs on the normalized set, so
                    // it holds even when the on-blur normalizer never fired.
                    ->rules([
                        fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                            $keys = collect(is_array($value) ? $value : [])
                                ->map(fn ($field): string => static::normalizeFieldName(
                                    is_array($field) ? ($field['name'] ?? null) : null,
                                ))
                                ->filter(fn (string $key): bool => $key !== '');

                            if ($keys->count() !== $keys->unique()->count()) {
                                $fail(__('content.forms.duplicate_field_name'));
                            }
                        },
                    ])
                    ->schema([
                        TextInput::make('name')
                            ->label(__('content.fields.field_name'))
                            ->helperText(fn (Get $get, $livewire): string => static::isLockedFieldName($livewire, $get('name'))
                                ? __('content.hints.field_name_locked')
                                : __('content.hints.field_name'))
                            ->required()
                            // Machine name used as the payload key and the
                            // validation attribute. Must be unique across
                            // the repeater: duplicates would overwrite
                            // validation rules and produce ambiguous
                            // payloads. distinct() catches identical raw
                            // input; the parent repeater's rule catches keys
                            // that only collide once normalized.
                            ->distinct()
                            // Format AND length are validated on the
                            // NORMALIZED key, never on the raw input — that
                            // key is what gets persisted. A raw maxLength()
                            // would reject "Full Name" style input whose
                            // stored key is both valid and well within the
                            // limit, and correctness must not depend on
                            // whether the blur hook below happened to fire.
                            ->rules([
                                fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                    $key = static::normalizeFieldName($value);

                                    if (! preg_match(self::FIELD_NAME_PATTERN, $key) || strlen($key) > 64) {
                                        $fail(__('content.forms.invalid_field_name'));
                                    }
                                },
                            ])
                            // Cosmetic only: normalizing on blur shows the
                            // administrator the key that will actually be
                            // stored, before they save.
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (TextInput $component, ?string $state): void {
                                $component->state(static::normalizeFieldName($state));
                            })
                            // A key that already carries collected data is
                            // frozen: renaming it would orphan every stored
                            // payload written under the old key. dehydrated()
                            // keeps the frozen value in the saved definition.
                            ->disabled(fn (Get $get, $livewire): bool => static::isLockedFieldName($livewire, $get('name')))
                            ->dehydrated(),

                        Select::make('type')
                            ->label(__('content.fields.field_type'))
                            ->options(collect(Form::FIELD_TYPES)
                                ->mapWithKeys(fn (string $type): array => [
                                    $type => __('content.field_types.'.$type),
                                ])
                                ->all())
                            // Options visibility and the consent-toggle
                            // label react to the chosen type.
                            ->live()
                            ->required(),

                        Toggle::make('required')
                            // For a checkbox, "required" means the visitor
                            // must tick it — consent semantics, enforced
                            // server-side via the 'accepted' rule.
                            ->label(fn (Get $get): string => $get('type') === 'checkbox'
                                ? __('content.fields.field_required_consent')
                                : __('content.fields.field_required'))
                            ->default(false),

                        ...collect(config('platform.supported_locales', ['en']))
                            ->map(fn (string $locale) => TextInput::make("label.{$locale}")
                                ->label(__('content.fields.field_label').' ('.strtoupper($locale).')')
                                ->required($locale === config('platform.default_locale', 'en'))
                                ->maxLength(255))
                            ->all(),

                        Repeater::make('options')
                            ->label(__('content.fields.field_options'))
                            ->visible(fn (Get $get): bool => $get('type') === 'select')
                            ->required(fn (Get $get): bool => $get('type') === 'select')
                            ->minItems(1)
                            ->maxItems(Form::MAX_SELECT_OPTIONS)
                            ->schema([
                                TextInput::make('value')
                                    ->label(__('content.fields.option_value'))
                                    ->required()
                                    ->maxLength(64)
                                    // Stable machine identifier — the same
                                    // pattern the model's sanitizer trusts.
                                    ->regex(Form::OPTION_VALUE_PATTERN)
                                    ->distinct(),

                                ...collect(config('platform.supported_locales', ['en']))
                                    ->map(fn (string $locale) => TextInput::make("label.{$locale}")
                                        ->label(__('content.fields.option_label').' ('.strtoupper($locale).')')
                                        ->required($locale === config('platform.default_locale', 'en'))
                                        ->maxLength(255))
                                    ->all(),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('content.fields.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('slug')
                    ->label(__('content.fields.slug'))
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label(__('content.fields.is_active'))
                    ->boolean(),

                TextColumn::make('submissions_count')
                    ->label(__('content.fields.submissions_count'))
                    ->counts('submissions'),

                TextColumn::make('created_at')
                    ->label(__('content.fields.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
        // Deliberately NO delete and NO bulk actions: the Contact form is a
        // fixed system record.
    }

    /**
     * Reduce an administrator's input to a safe lowercase snake_case machine
     * key.
     *
     * Nothing is invented, so normalization never fabricates a valid key out
     * of an invalid one. "123 Name" normalizes to "123_name" and is returned
     * in that form — it has no leading letter, so the field's regex rule
     * rejects it. A value containing no machine characters at all (an
     * Arabic-only key) would normalize to an empty string; the ORIGINAL is
     * returned instead, so the rule reports the format rather than the
     * administrator watching their input silently vanish.
     */
    public static function normalizeFieldName(mixed $name): string
    {
        if (! is_string($name)) {
            return '';
        }

        $name = trim($name);

        // Str::snake first, so "FullName" and "Full Name" both reach
        // "full_name"; then collapse anything left that is not a machine
        // character (dashes, punctuation) into a single underscore.
        $normalized = preg_replace('/[^a-z0-9]+/', '_', Str::lower(Str::snake($name))) ?? '';
        $normalized = trim($normalized, '_');

        return $normalized === '' ? $name : $normalized;
    }

    /**
     * Normalize every field's machine key and strip stale option definitions
     * from non-select fields before saving.
     *
     * Both run on the resolved form data, so they cover every save path —
     * including one where the on-blur normalizer never fired.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeFieldDefinitions(array $data): array
    {
        if (is_array($data['fields'] ?? null)) {
            $data['fields'] = collect($data['fields'])
                ->map(function ($field) {
                    if (! is_array($field)) {
                        return $field;
                    }

                    if (array_key_exists('name', $field)) {
                        $field['name'] = static::normalizeFieldName($field['name']);
                    }

                    if (($field['type'] ?? null) !== 'select') {
                        unset($field['options']);
                    }

                    return $field;
                })
                ->all();
        }

        return $data;
    }

    /**
     * Whether a repeater item's machine key is frozen: it is already part of
     * the saved definition AND the form has collected submissions, so stored
     * payloads are keyed by it.
     *
     * The submissions count is loaded onto the record once and reused, so a
     * 20-field definition costs one query per render rather than twenty.
     */
    public static function isLockedFieldName(mixed $livewire, mixed $name): bool
    {
        if (! is_string($name) || $name === '' || ! $livewire instanceof EditRecord) {
            return false;
        }

        $record = $livewire->getRecord();

        if (! $record instanceof Form) {
            return false;
        }

        if (! array_key_exists('submissions_count', $record->getAttributes())) {
            $record->loadCount('submissions');
        }

        if ((int) $record->submissions_count === 0) {
            return false;
        }

        return in_array($name, $record->fieldNames(), true);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListForms::route('/'),
            'edit' => EditForm::route('/{record}/edit'),
        ];
    }
}
