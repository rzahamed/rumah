<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FormResource\Pages\CreateForm;
use App\Filament\Resources\FormResource\Pages\EditForm;
use App\Filament\Resources\FormResource\Pages\ListForms;
use App\Models\Form;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class FormResource extends Resource
{
    protected static ?string $model = Form::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('nav.groups.forms');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('content.fields.name'))
                    ->required()
                    ->maxLength(255),

                TextInput::make('slug')
                    ->label(__('content.fields.slug'))
                    ->required()
                    ->maxLength(255)
                    ->regex('/^[a-z0-9-]+$/')
                    ->unique(ignoreRecord: true),

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
                    ->schema([
                        TextInput::make('name')
                            ->label(__('content.fields.field_name'))
                            ->required()
                            ->maxLength(64)
                            // Machine name used as the payload key and the
                            // validation attribute. Must be unique across
                            // the repeater: duplicates would overwrite
                            // validation rules and produce ambiguous
                            // payloads.
                            ->distinct()
                            ->regex('/^[a-z][a-z0-9_]*$/'),

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
                static::configureDeleteAction(DeleteAction::make()),
            ]);
    }

    /**
     * Strip stale option definitions from non-select fields before saving:
     * switching a field's type away from select must never persist its old
     * options. Used by both the create and edit pages.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeFieldDefinitions(array $data): array
    {
        if (is_array($data['fields'] ?? null)) {
            $data['fields'] = collect($data['fields'])
                ->map(function ($field) {
                    if (is_array($field) && ($field['type'] ?? null) !== 'select') {
                        unset($field['options']);
                    }

                    return $field;
                })
                ->all();
        }

        return $data;
    }

    /**
     * Deleting a form that has collected submissions is blocked at the
     * database level (restrictOnDelete); this guard communicates the
     * restriction cleanly instead of surfacing a constraint error, and runs
     * for every actor including Gate-bypassing super admins.
     */
    public static function configureDeleteAction(DeleteAction $action): DeleteAction
    {
        return $action->before(function (DeleteAction $action, Form $record): void {
            if ($record->submissions()->exists()) {
                Notification::make()
                    ->title(__('content.forms.delete_blocked'))
                    ->danger()
                    ->send();

                $action->cancel();
            }
        });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListForms::route('/'),
            'create' => CreateForm::route('/create'),
            'edit' => EditForm::route('/{record}/edit'),
        ];
    }
}
