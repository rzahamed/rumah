<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NewsletterSubscriberResource\Pages\ListNewsletterSubscribers;
use App\Models\NewsletterSubscriber;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * PROTECTED ADMIN DATA with a structurally read-only surface: list + export
 * + individual delete ONLY. The restrictions are enforced HERE — not just
 * in NewsletterSubscriberPolicy — because active super admins bypass
 * policies via Gate::before: no create/edit/view routes exist, the static
 * can* overrides below short-circuit before the Gate, and no bulk actions
 * are registered.
 */
class NewsletterSubscriberResource extends Resource
{
    protected static ?string $model = NewsletterSubscriber::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('nav.groups.forms');
    }

    public static function getModelLabel(): string
    {
        return __('content.subscribers.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('content.subscribers.plural');
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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('email')
                    ->label(__('content.fields.email'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('locale')
                    ->label(__('content.fields.locale'))
                    ->badge()
                    ->sortable(),

                TextColumn::make('consented_at')
                    ->label(__('content.fields.consented_at'))
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('content.fields.subscribed_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('locale')
                    ->label(__('content.fields.locale'))
                    ->options(fn (): array => collect(config('platform.supported_locales', ['en']))
                        ->mapWithKeys(fn (string $locale): array => [$locale => strtoupper($locale)])
                        ->all()),
            ])
            ->recordActions([
                DeleteAction::make(),
            ]);
        // Deliberately NO bulk actions: collected data is deleted one
        // record at a time or not at all.
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNewsletterSubscribers::route('/'),
        ];
    }
}
