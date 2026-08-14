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
use Illuminate\Database\Eloquent\Builder;
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

    public static function getModelLabel(): string
    {
        return __('users.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('users.plural');
    }

    /**
     * Super administrators are INVISIBLE to everyone who is not one.
     *
     * Scoped at the query level, so the exclusion applies to the list table,
     * its search and filters, and record route binding alike — Filament
     * resolves {record} through this same query, so a hand-typed edit URL
     * for a super admin resolves to nothing and 404s. Filtering rows in PHP
     * after retrieval would leak both the count and the ids.
     *
     * This is discovery prevention only. UserPolicy separately refuses view,
     * update and delete on a super-admin target, because a forged Livewire
     * call can name a record without ever going through this query.
     */
    public static function getEloquentQuery(): Builder
    {
        // The rule itself lives in User::scopeVisibleToActor(), shared with
        // notification-recipient selection so the two cannot diverge.
        return parent::getEloquentQuery()->visibleToActor(auth()->user());
    }

    /**
     * Creating a user is an invitation, and every invitation now carries
     * exactly one role — so role authority is part of being able to create
     * at all. Without this, an actor holding users.create but NOT
     * users.manage_roles would reach the form with the role field hidden and
     * produce a ROLELESS user. Denying the path is safer than silently
     * picking a role on their behalf.
     */
    public static function canCreate(): bool
    {
        return parent::canCreate()
            && (bool) auth()->user()?->can('manageRoles', User::class);
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

                // Deliberately named 'role', NOT 'roles': the product model is
                // mutually exclusive, and a field named after Spatie's real
                // BelongsToMany relationship can render single-select while
                // Filament still round-trips array state. A non-relationship
                // key makes the scalar shape structural rather than a
                // by-product of how the relationship is handled.
                Select::make('role')
                    ->label(__('users.fields.role'))
                    ->required()
                    // Three options: a plain native select needs no search or
                    // preload machinery.
                    ->native()
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
                    // Translated, non-sensitive server-side rules for forged
                    // payloads — independent of the visible options. The
                    // AUTHORITATIVE guards live in InviteUser::invite() and
                    // EditUser::handleRecordUpdate().
                    ->rules([
                        // Outer closure is evaluated by Filament (with
                        // utility injection) and RETURNS the Laravel rule.
                        fn (string $operation): Closure => function (string $attribute, mixed $value, Closure $fail) use ($operation): void {
                            // Shape first, on BOTH operations: exactly one
                            // scalar role name. A crafted array is rejected
                            // outright rather than coerced — casting it would
                            // silently accept a multi-role submission.
                            if (! is_string($value) || $value === '') {
                                $fail(__('users.guards.roles_invalid'));

                                return;
                            }

                            if ($operation !== 'create') {
                                return;
                            }

                            if (! static::actorIsActiveSuperAdmin() && $value === 'super_admin') {
                                $fail(__('users.guards.super_admin_invite'));
                            }
                        },
                    ])
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
                    ->label(__('users.fields.role'))
                    ->badge()
                    // The column reads the machine name; administrators must
                    // see the translated label. Relationship state arrives as
                    // a scalar OR an array depending on how Filament resolves
                    // it, so both shapes are mapped.
                    ->formatStateUsing(fn (mixed $state): mixed => match (true) {
                        is_string($state) => static::roleLabel($state),
                        is_array($state) => array_map(
                            fn (mixed $role): mixed => is_string($role)
                                ? static::roleLabel($role)
                                : $role,
                            $state,
                        ),
                        default => $state,
                    }),

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
     * The single definition of "acting as a super administrator": an active
     * holder of the guard-scoped super_admin role. Status matters because
     * Gate::before only grants for active holders, so an inactive one must
     * not inherit super-admin visibility here either.
     */
    private static function actorIsActiveSuperAdmin(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User
            && $actor->status === UserStatus::Active
            && $actor->hasRole('super_admin');
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
        return Role::query()
            ->where('guard_name', config('auth.defaults.guard'))
            ->when(
                ! static::actorIsActiveSuperAdmin(),
                fn ($query) => $query->where('name', '!=', 'super_admin'),
            )
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
