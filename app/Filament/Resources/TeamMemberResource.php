<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TeamMemberResource\Pages\CreateTeamMember;
use App\Filament\Resources\TeamMemberResource\Pages\EditTeamMember;
use App\Filament\Resources\TeamMemberResource\Pages\ListTeamMembers;
use App\Filament\Support\LedgeredUpload;
use App\Filament\Support\TranslatableInputs;
use App\Models\TeamMember;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
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

    public static function getModelLabel(): string
    {
        return __('content.team.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('content.team.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                ...TranslatableInputs::text('name', __('content.fields.name')),

                ...TranslatableInputs::text('position', __('content.fields.position')),

                ...TranslatableInputs::textarea('bio', __('content.fields.bio'), required: false, rows: 4),

                // Public professional contact address, rendered only in the
                // team biography modal.
                TextInput::make('email')
                    ->label(__('content.fields.email'))
                    ->email()
                    ->maxLength(255)
                    ->nullable(),

                FileUpload::make('photo_path')
                    ->label(__('content.fields.photo'))
                    // Env-driven disk selection. CURRENT PHASE: 'public' in
                    // both local and production (VM storage, served through
                    // the public/storage symlink). A GCS disk is a deferred
                    // future migration, switchable via MEDIA_DISK with no
                    // code change.
                    ->disk(config('platform.media_disk'))
                    ->directory('team')
                    ->visibility('public')
                    ->image()
                    ->maxSize(2048)
                    ->nullable()
                    ->saveUploadedFileUsing(LedgeredUpload::saveUsing()),

                Repeater::make('highlights')
                    ->label(__('content.fields.highlights'))
                    ->maxItems(10)
                    ->defaultItems(0)
                    ->schema(TranslatableInputs::flatText(__('content.fields.highlight'))),

                Repeater::make('credentials')
                    ->label(__('content.fields.credentials'))
                    ->maxItems(10)
                    ->defaultItems(0)
                    ->schema([
                        ...TranslatableInputs::text('title', __('content.fields.credential_title')),
                        ...TranslatableInputs::text('institution', __('content.fields.credential_institution'), required: false),
                        ...TranslatableInputs::textarea('description', __('content.fields.credential_description'), required: false, rows: 2, maxLength: 1000),
                    ]),

                Repeater::make('expertise')
                    ->label(__('content.fields.expertise'))
                    ->maxItems(20)
                    ->defaultItems(0)
                    ->schema(TranslatableInputs::flatText(__('content.fields.expertise_item'))),

                FileUpload::make('licence_image_path')
                    ->label(__('content.fields.licence_image'))
                    ->disk(config('platform.media_disk'))
                    ->directory('team/licences')
                    ->visibility('public')
                    ->image()
                    // Raster web formats only — SVG is scriptable and is
                    // deliberately excluded from public asset uploads.
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(2048)
                    ->nullable()
                    ->saveUploadedFileUsing(LedgeredUpload::saveUsing()),

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
