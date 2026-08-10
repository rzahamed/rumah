<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FaqResource\Pages\CreateFaq;
use App\Filament\Resources\FaqResource\Pages\EditFaq;
use App\Filament\Resources\FaqResource\Pages\ListFaqs;
use App\Filament\Support\TranslatableInputs;
use App\Models\Faq;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class FaqResource extends Resource
{
    protected static ?string $model = Faq::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static ?int $navigationSort = 4;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('nav.groups.content');
    }

    /**
     * Overridden because the derived label would read "Faq" — the one
     * acronym-cased model in the panel.
     */
    public static function getModelLabel(): string
    {
        return __('content.faq.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('content.faq.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                ...TranslatableInputs::text('question', __('content.fields.question')),

                ...TranslatableInputs::textarea('answer', __('content.fields.answer'), rows: 4),

                TextInput::make('sort_order')
                    ->label(__('content.fields.sort_order'))
                    ->integer()
                    ->default(0)
                    ->required(),

                Toggle::make('is_visible')
                    ->label(__('content.fields.is_visible'))
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('question')
                    ->label(__('content.fields.question'))
                    ->state(fn (Faq $record): ?string => $record->translate('question')),

                TextColumn::make('sort_order')
                    ->label(__('content.fields.sort_order'))
                    ->sortable(),

                IconColumn::make('is_visible')
                    ->label(__('content.fields.is_visible'))
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label(__('content.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFaqs::route('/'),
            'create' => CreateFaq::route('/create'),
            'edit' => EditFaq::route('/{record}/edit'),
        ];
    }
}
