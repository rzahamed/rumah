<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages\CreateCategory;
use App\Filament\Resources\CategoryResource\Pages\EditCategory;
use App\Filament\Resources\CategoryResource\Pages\ListCategories;
use App\Filament\Support\TranslatableInputs;
use App\Models\Category;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('nav.groups.content');
    }

    public static function getModelLabel(): string
    {
        return __('content.categories.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('content.categories.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                ...TranslatableInputs::text('name', __('content.fields.name')),

                TextInput::make('slug')
                    ->label(__('content.fields.slug'))
                    ->required()
                    ->maxLength(255)
                    ->regex('/^[a-z0-9-]+$/')
                    ->unique(ignoreRecord: true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('content.fields.name'))
                    ->state(fn (Category $record): ?string => $record->translate('name'))
                    ->searchable(query: fn ($query, string $search) => $query->where('slug', 'ilike', "%{$search}%")),

                TextColumn::make('slug')
                    ->label(__('content.fields.slug'))
                    ->sortable(),

                TextColumn::make('posts_count')
                    ->label(__('content.fields.posts_count'))
                    ->counts('posts'),

                TextColumn::make('created_at')
                    ->label(__('content.fields.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCategories::route('/'),
            'create' => CreateCategory::route('/create'),
            'edit' => EditCategory::route('/{record}/edit'),
        ];
    }
}
