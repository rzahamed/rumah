<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TeamMemberResource\Pages\CreateTeamMember;
use App\Filament\Resources\TeamMemberResource\Pages\EditTeamMember;
use App\Filament\Resources\TeamMemberResource\Pages\ListTeamMembers;
use App\Filament\Support\TranslatableInputs;
use App\Models\TeamMember;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class TeamMemberResource extends Resource
{
    protected static ?string $model = TeamMember::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('nav.groups.content');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                ...TranslatableInputs::text('name', __('content.fields.name')),

                ...TranslatableInputs::text('position', __('content.fields.position')),

                ...TranslatableInputs::textarea('bio', __('content.fields.bio'), required: false, rows: 4),

                FileUpload::make('photo_path')
                    ->label(__('content.fields.photo'))
                    // Env-driven public/private arrangement: local 'public'
                    // in development, a GCS disk in production via
                    // MEDIA_DISK — no code change.
                    ->disk(config('platform.media_disk'))
                    ->directory('team')
                    ->visibility('public')
                    ->image()
                    ->maxSize(2048)
                    ->nullable(),

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
                TextColumn::make('name')
                    ->label(__('content.fields.name'))
                    ->state(fn (TeamMember $record): ?string => $record->translate('name')),

                TextColumn::make('position')
                    ->label(__('content.fields.position'))
                    ->state(fn (TeamMember $record): ?string => $record->translate('position')),

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
            'index' => ListTeamMembers::route('/'),
            'create' => CreateTeamMember::route('/create'),
            'edit' => EditTeamMember::route('/{record}/edit'),
        ];
    }
}
