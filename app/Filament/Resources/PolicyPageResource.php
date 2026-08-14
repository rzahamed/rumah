<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PolicyPageResource\Pages\EditPolicyPage;
use App\Filament\Resources\PolicyPageResource\Pages\ListPolicyPages;
use App\Filament\Support\TranslatableInputs;
use App\Models\PolicyPage;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * LEGAL CONTENT with a structurally narrow surface: list + edit ONLY. The
 * two canonical rows are provisioned by migration, so no create route
 * exists; deletion is disabled outright. Restrictions are enforced HERE as
 * well as in PolicyPagePolicy, because active super admins bypass policies
 * via Gate::before.
 *
 * The key is displayed but never editable — footer links, the newsletter
 * consent line and the public routes all depend on its exact value.
 *
 * Bodies stay OPTIONAL so a policy can be drafted incrementally. The rule
 * that a published policy must actually have text is enforced at save time
 * in EditPolicyPage::beforeSave(), which covers every save path rather than
 * depending on conditional field-state rules.
 */
class PolicyPageResource extends Resource
{
    protected static ?string $model = PolicyPage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static ?int $navigationSort = 4;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('nav.groups.content');
    }

    public static function getModelLabel(): string
    {
        return __('content.policies.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('content.policies.plural');
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
                // Shown for orientation, never writable: the key is the
                // contract the routes and footer links depend on.
                TextInput::make('key')
                    ->label(__('content.fields.policy_key'))
                    ->disabled()
                    ->dehydrated(false),

                ...TranslatableInputs::text('title', __('content.fields.title')),

                ...collect(TranslatableInputs::textarea('body', __('content.fields.body'), required: false, rows: 20))
                    ->map(fn (Textarea $field): Textarea => $field->helperText(__('content.hints.body_markdown')))
                    ->all(),

                Toggle::make('is_published')
                    ->label(__('content.fields.is_published'))
                    ->helperText(__('content.hints.policy_publication')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('content.fields.title'))
                    ->state(fn (PolicyPage $record): ?string => $record->translate('title')),

                TextColumn::make('key')
                    ->label(__('content.fields.policy_key'))
                    ->badge(),

                IconColumn::make('is_published')
                    ->label(__('content.fields.is_published'))
                    ->boolean(),

                TextColumn::make('updated_at')
                    ->label(__('content.fields.updated_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('key')
            ->recordActions([
                EditAction::make(),
            ]);
        // Deliberately NO delete and NO bulk actions: the two rows are fixed
        // system records.
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPolicyPages::route('/'),
            'edit' => EditPolicyPage::route('/{record}/edit'),
        ];
    }
}
