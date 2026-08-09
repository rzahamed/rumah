<?php

namespace App\Filament\Resources;

use App\Actions\InviteUser;
use App\Enums\UserStatus;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\User;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\HtmlString;
use LogicException;
use Spatie\Permission\Models\Role;
use UnitEnum;

/**
 * User administration, permission-gated through UserPolicy. Creation is an
 * INVITATION (no password fields anywhere — passwords exist only via the
 * acceptance form and password reset). Status and roles are never
 * mass-assigned: the create path hands them to the InviteUser action and
 * the edit path validates them explicitly in EditUser::handleRecordUpdate.
 * Delete guards (self, last active super admin) live in action before-hooks
 * so they bind active super admins too, whom Gate::before exempts from the
 * policy.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('nav.groups.administration');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('users.fields.name'))
                    ->required()
                    ->maxLength(255),

                TextInput::make('email')
                    ->label(__('users.fields.email'))
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),

                Select::make('status')
                    ->label(__('users.fields.status'))
                    ->options([
                        UserStatus::Active->value => UserStatus::Active->getLabel(),
                        UserStatus::Inactive->value => UserStatus::Inactive->getLabel(),
                    ])
                    ->required()
                    ->hiddenOn('create')
                    // Invited accounts activate ONLY through acceptance;
                    // disabled fields are not submitted, so the status of an
                    // invited record cannot change here.
                    ->disabled(fn (?User $record): bool => $record?->status === UserStatus::Invited),

                Select::make('roles')
                    ->label(__('users.fields.roles'))
                    ->multiple()
                    // Guard-scoped options; on the CREATE (invite) page the
                    // set additionally excludes super_admin for non-super
                    // actors. The edit form keeps the full set unchanged.
                    ->options(function (string $operation): array {
                        $names = $operation === 'create'
                            ? static::inviteAssignableRoleNames()
                            : Role::query()
                                ->where('guard_name', config('auth.defaults.guard'))
                                ->orderBy('name')
                                ->pluck('name')
                                ->all();

                        return collect($names)
                            ->mapWithKeys(fn (string $name): array => [$name => static::roleLabel($name)])
                            ->all();
                    })
                    // Translated, non-sensitive server-side rule for forged
                    // invite payloads — independent of the visible options.
                    // The AUTHORITATIVE guard lives in InviteUser::invite().
                    ->rules([
                        // Outer closure is evaluated by Filament (with
                        // utility injection) and RETURNS the Laravel rule.
                        fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                            $actor = auth()->user();
                            $actorIsActiveSuperAdmin = $actor?->status === UserStatus::Active
                                && $actor->hasRole('super_admin');

                            if (! $actorIsActiveSuperAdmin && in_array('super_admin', (array) $value, true)) {
                                $fail(__('users.guards.super_admin_invite'));
                            }
                        },
                    ], condition: fn (string $operation): bool => $operation === 'create')
                    // Visibility is UX only — both write paths re-check
                    // authorization server-side (InviteUser internally; the
                    // edit save path via the manageRoles policy ability).
                    ->visible(fn (?User $record): bool => (bool) auth()->user()?->can('manageRoles', $record ?? User::class)),

                Placeholder::make('roles_information')
                    ->hiddenLabel()
                    ->visible(fn (string $operation): bool => $operation === 'create'
                        && (bool) auth()->user()?->can('manageRoles', User::class))
                    ->content(function (): HtmlString {
                        $items = collect(static::inviteAssignableRoleNames())
                            ->filter(fn (string $name): bool => Lang::has("users.roles.{$name}.description"))
                            ->map(fn (string $name): string => '<li><span style="font-weight:600;">'
                                .e(static::roleLabel($name)).':</span> '
                                .e(__("users.roles.{$name}.description")).'</li>')
                            ->implode('');

                        $icon = svg('heroicon-o-information-circle', '', [
                            'style' => 'width:1.25rem;height:1.25rem;flex-shrink:0;color:rgb(107,114,128);',
                            'aria-hidden' => 'true',
                        ])->toHtml();

                        // Only non-directional spacing (flex gap + symmetric
                        // padding), so LTR and RTL both render correctly.
                        return new HtmlString(
                            '<div style="display:flex;align-items:flex-start;gap:0.625rem;background-color:rgb(249,250,251);border:1px solid rgb(229,231,235);border-radius:0.75rem;padding:0.875rem 1rem;">'
                            .$icon
                            .'<div style="display:flex;flex-direction:column;gap:0.375rem;">'
                            .'<span style="font-weight:600;font-size:0.875rem;color:rgb(55,65,81);">'.e(__('users.roles_info.heading')).'</span>'
                            .'<ul style="display:flex;flex-direction:column;gap:0.375rem;font-size:0.875rem;color:rgb(82,82,91);margin:0;padding:0;list-style:none;">'
                            .$items
                            .'</ul>'
                            .'</div>'
                            .'</div>'
                        );
                    }),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('users.fields.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label(__('users.fields.email'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->label(__('users.fields.status'))
                    ->badge()
                    ->sortable(),

                TextColumn::make('roles.name')
                    ->label(__('users.fields.roles'))
                    ->badge(),

                TextColumn::make('last_login_at')
                    ->label(__('users.fields.last_login_at'))
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('created_at')
                    ->label(__('users.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('users.fields.status'))
                    ->options([
                        UserStatus::Active->value => UserStatus::Active->getLabel(),
                        UserStatus::Inactive->value => UserStatus::Inactive->getLabel(),
                        UserStatus::Invited->value => UserStatus::Invited->getLabel(),
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                static::resendInvitationAction(),
                static::configureDeleteAction(DeleteAction::make()),
            ]);
        // No bulk actions by design: UserPolicy::deleteAny() is false and
        // per-record guards must always run.
    }

    /**
     * Shared delete guards, applied to both the table row action and the
     * edit-page header action. Runs for EVERY actor — including active super
     * admins, who bypass UserPolicy via Gate::before.
     */
    public static function configureDeleteAction(DeleteAction $action): DeleteAction
    {
        return $action->before(function (DeleteAction $action, User $record): void {
            // The last-super check runs FIRST: when both guards apply (the
            // last active super admin deleting themself) the more critical
            // guard is the one that fires and reports itself — and each
            // branch stays independently provable in tests.
            if ($record->isLastActiveSuperAdmin()) {
                Notification::make()
                    ->title(__('users.guards.last_super_admin_delete'))
                    ->danger()
                    ->send();

                $action->cancel();
            }

            if (auth()->user()?->is($record)) {
                Notification::make()
                    ->title(__('users.guards.self_delete'))
                    ->danger()
                    ->send();

                $action->cancel();
            }
        });
    }

    public static function resendInvitationAction(): Action
    {
        return Action::make('resendInvitation')
            ->label(__('users.actions.resend_invitation'))
            ->requiresConfirmation()
            ->visible(fn (User $record): bool => $record->status === UserStatus::Invited
                && (bool) auth()->user()?->can('create', User::class))
            ->action(function (User $record): void {
                // Expected failures map to translated notifications; anything
                // unexpected propagates untouched.
                try {
                    app(InviteUser::class)->resend(auth()->user(), $record);
                } catch (AuthorizationException) {
                    Notification::make()
                        ->title(__('users.actions.resend_unauthorized'))
                        ->danger()
                        ->send();

                    return;
                } catch (LogicException) {
                    Notification::make()
                        ->title(__('users.actions.resend_not_invited'))
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title(__('users.actions.invitation_resent'))
                    ->success()
                    ->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }

    /**
     * Roles the current actor may hand out in the INVITE flow: guard-scoped,
     * and super_admin only when the actor is an active super administrator.
     * Mirrors the server-side rule enforced in InviteUser::invite().
     *
     * @return list<string>
     */
    private static function inviteAssignableRoleNames(): array
    {
        $actor = auth()->user();
        $actorIsActiveSuperAdmin = $actor?->status === UserStatus::Active
            && $actor->hasRole('super_admin');

        return Role::query()
            ->where('guard_name', config('auth.defaults.guard'))
            ->when(! $actorIsActiveSuperAdmin, fn ($query) => $query->where('name', '!=', 'super_admin'))
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

    /**
     * Translated role label with a safe fallback to the raw name for
     * client-added roles without translations; always escaped at render.
     */
    private static function roleLabel(string $name): string
    {
        return Lang::has("users.roles.{$name}.label") ? __("users.roles.{$name}.label") : $name;
    }
}
