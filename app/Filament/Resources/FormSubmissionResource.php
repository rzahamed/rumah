<?php

namespace App\Filament\Resources;

use App\Enums\SubmissionStatus;
use App\Filament\Resources\FormSubmissionResource\Pages\ListFormSubmissions;
use App\Filament\Resources\FormSubmissionResource\Pages\ViewFormSubmission;
use App\Models\FormSubmission;
use App\Support\SubmissionPayload;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * PROTECTED ADMIN DATA with a structurally read-only surface: list + view +
 * individual delete ONLY. The restrictions are enforced HERE — not just in
 * FormSubmissionPolicy — because active super admins bypass policies via
 * Gate::before: no create/edit routes exist, the static can* overrides
 * below short-circuit before the Gate, the view form is fully disabled, and
 * no bulk actions are registered.
 */
class FormSubmissionResource extends Resource
{
    protected static ?string $model = FormSubmission::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('nav.groups.forms');
    }

    public static function getModelLabel(): string
    {
        return __('content.submissions.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('content.submissions.plural');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    /**
     * The record page is an INFOLIST, not a disabled form: entries cannot be
     * dehydrated at all, so there is no write path to disable in the first
     * place. Every value renders as escaped text — never ->html() — and long
     * values wrap rather than widening the page.
     */
    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('content.submissions.contact_section'))
                    ->schema([
                        TextEntry::make('contact_name')
                            ->label(__('content.fields.contact_name'))
                            ->state(fn (FormSubmission $record): ?string => SubmissionPayload::for($record)->name())
                            ->placeholder(SubmissionPayload::PLACEHOLDER),

                        TextEntry::make('contact_email')
                            ->label(__('content.fields.email'))
                            ->state(fn (FormSubmission $record): ?string => SubmissionPayload::for($record)->email())
                            ->placeholder(SubmissionPayload::PLACEHOLDER)
                            ->copyable()
                            ->extraAttributes(['style' => 'overflow-wrap: anywhere;']),

                        TextEntry::make('contact_phone')
                            ->label(__('content.fields.phone'))
                            ->state(fn (FormSubmission $record): ?string => SubmissionPayload::for($record)->phone())
                            ->placeholder(SubmissionPayload::PLACEHOLDER)
                            ->copyable(),

                        TextEntry::make('contact_interest')
                            ->label(__('content.fields.case_interest'))
                            ->state(fn (FormSubmission $record): ?string => SubmissionPayload::for($record)->interest())
                            ->placeholder(SubmissionPayload::PLACEHOLDER),
                    ])
                    ->columns(2),

                /*
                 * EVERY submitted field, in definition order, as ordinary
                 * label/value entries — the same compact visual language as
                 * Record details below.
                 *
                 * One entry PER FIELD rather than a repeatable list of
                 * "Field / Answer" rows: that nested structure repeated two
                 * generic headings for every answer and wasted most of the
                 * width. Entries are keyed by the payload KEY (unique by
                 * definition) and merely labelled with the translated label,
                 * so two fields sharing a label still render both answers —
                 * keying by label would drop one.
                 *
                 * The message field is included here rather than duplicated
                 * into a section of its own; long values span the full width.
                 */
                Section::make(__('content.submissions.all_fields_section'))
                    ->schema(fn (FormSubmission $record): array => collect(SubmissionPayload::for($record)->all())
                        ->map(fn (array $row): TextEntry => TextEntry::make('payload.'.$row['key'])
                            ->label($row['label'])
                            ->state($row['value'])
                            ->placeholder(SubmissionPayload::PLACEHOLDER)
                            // pre-wrap keeps newlines visible without the
                            // value ever being parsed as markup.
                            ->extraAttributes(['style' => 'white-space: pre-wrap; overflow-wrap: anywhere;'])
                            // Long or multi-line answers get the full row;
                            // short ones sit two-up on desktop.
                            ->columnSpanFull(static::isLongValue($row['value'])))
                        ->all())
                    ->columns(['default' => 1, 'md' => 2]),

                Section::make(__('content.submissions.metadata_section'))
                    ->schema([
                        // Current triage state. Changing it is the separately
                        // authorized header action, never this entry.
                        TextEntry::make('status')
                            ->label(__('content.fields.status'))
                            ->badge(),

                        TextEntry::make('form.name')
                            ->label(__('content.fields.form'))
                            ->placeholder(SubmissionPayload::PLACEHOLDER),

                        TextEntry::make('created_at')
                            ->label(__('content.fields.submitted_at'))
                            ->dateTime(),

                        TextEntry::make('reviewed_at')
                            ->label(__('content.fields.reviewed_at'))
                            ->dateTime()
                            ->placeholder(SubmissionPayload::PLACEHOLDER),

                        TextEntry::make('reviewer.name')
                            ->label(__('content.fields.reviewed_by'))
                            ->placeholder(SubmissionPayload::PLACEHOLDER),
                    ])
                    ->columns(2),
                // No source, IP address or user agent: this table has never
                // collected requester metadata, and that stays true.
            ]);
    }

    /**
     * Eager-load the form: every payload-derived column resolves through the
     * form definition, so without this each row would issue its own query.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('form');
    }

    /**
     * Whether an answer should occupy the full row rather than sit two-up.
     *
     * Multi-line answers and long prose (a case description, say) are
     * unreadable in a half-width column; short answers like a phone number
     * would waste the row. 120 characters is the crossover chosen for the
     * panel's content width.
     */
    private static function isLongValue(string $value): bool
    {
        return str_contains($value, "\n") || mb_strlen($value) > 120;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // Contact identity: the core of the row, never toggled off
                // by default. Values come from SubmissionPayload, the single
                // place payload keys are interpreted; a renamed key degrades
                // to an em dash rather than showing the wrong answer.
                TextColumn::make('contact_name')
                    ->label(__('content.fields.contact_name'))
                    ->state(fn (FormSubmission $record): ?string => SubmissionPayload::for($record)->name())
                    ->placeholder(SubmissionPayload::PLACEHOLDER)
                    ->searchable(query: fn (Builder $query, string $search): Builder => SubmissionPayload::scopeSearch($query, 'name', $search))
                    ->wrap(),

                TextColumn::make('contact_email')
                    ->label(__('content.fields.email'))
                    ->state(fn (FormSubmission $record): ?string => SubmissionPayload::for($record)->email())
                    ->placeholder(SubmissionPayload::PLACEHOLDER)
                    ->url(fn (FormSubmission $record): ?string => ($email = SubmissionPayload::for($record)->email()) === null
                        ? null
                        : 'mailto:'.$email)
                    ->copyable()
                    ->searchable(query: fn (Builder $query, string $search): Builder => SubmissionPayload::scopeSearch($query, 'email', $search))
                    ->wrap(),

                TextColumn::make('contact_phone')
                    ->label(__('content.fields.phone'))
                    ->state(fn (FormSubmission $record): ?string => SubmissionPayload::for($record)->phone())
                    ->placeholder(SubmissionPayload::PLACEHOLDER)
                    ->url(fn (FormSubmission $record): ?string => ($phone = SubmissionPayload::for($record)->phone()) === null
                        ? null
                        : 'tel:'.preg_replace('/[^0-9+]/', '', $phone))
                    ->copyable()
                    ->searchable(query: fn (Builder $query, string $search): Builder => SubmissionPayload::scopeSearch($query, 'phone', $search)),

                TextColumn::make('contact_interest')
                    ->label(__('content.fields.case_interest'))
                    ->state(fn (FormSubmission $record): ?string => SubmissionPayload::for($record)->interest())
                    ->placeholder(SubmissionPayload::PLACEHOLDER)
                    ->searchable(query: fn (Builder $query, string $search): Builder => SubmissionPayload::scopeSearch($query, 'interest', $search))
                    ->wrap(),

                TextColumn::make('status')
                    ->label(__('content.fields.status'))
                    ->badge()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('content.fields.submitted_at'))
                    ->dateTime()
                    ->sortable(),

                // Only ever one public form today, so the name adds little
                // per row — available, but off by default to keep the table
                // readable. Contact identity is never treated this way.
                TextColumn::make('form.name')
                    ->label(__('content.fields.form'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('content.fields.status'))
                    ->options(SubmissionStatus::options()),

                SelectFilter::make('form_id')
                    ->label(__('content.fields.form'))
                    ->relationship('form', 'name'),
            ])
            ->recordActions([
                ViewAction::make(),
                DeleteAction::make(),
            ]);
        // Deliberately NO bulk actions: collected data is deleted one
        // record at a time or not at all.
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFormSubmissions::route('/'),
            'view' => ViewFormSubmission::route('/{record}'),
        ];
    }
}
