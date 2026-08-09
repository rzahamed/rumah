<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FormSubmissionResource\Pages\ListFormSubmissions;
use App\Filament\Resources\FormSubmissionResource\Pages\ViewFormSubmission;
use App\Models\FormSubmission;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
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

    public static function form(Schema $schema): Schema
    {
        // Rendered only by the view page; every component is disabled and
        // nothing is ever dehydrated back.
        return $schema
            ->components([
                TextInput::make('form_name')
                    ->label(__('content.fields.form'))
                    ->disabled(),

                TextInput::make('created_at')
                    ->label(__('content.fields.submitted_at'))
                    ->disabled(),

                KeyValue::make('payload')
                    ->label(__('content.fields.payload'))
                    ->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('form.name')
                    ->label(__('content.fields.form'))
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('content.fields.submitted_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
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
