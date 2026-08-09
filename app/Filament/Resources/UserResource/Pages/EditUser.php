<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Enums\UserStatus;
use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            UserResource::configureDeleteAction(DeleteAction::make()),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['roles'] = $this->getRecord()->getRoleNames()->all();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $record */
        $actor = auth()->user();

        $requestedRoles = null;

        if (array_key_exists('roles', $data)) {
            $rawRoles = $data['roles'] ?? [];

            if (
                ! is_array($rawRoles)
                || collect($rawRoles)->contains(
                    fn (mixed $value): bool => ! is_string($value) || $value === '',
                )
            ) {
                throw ValidationException::withMessages([
                    'data.roles' => __('users.guards.roles_invalid'),
                ]);
            }

            $requestedRoles = collect($rawRoles)
                ->unique()
                ->sort()
                ->values()
                ->all();
        }

        $submittedStatus = null;

        if (array_key_exists('status', $data) && filled($data['status'])) {
            if (! is_string($data['status'])) {
                throw ValidationException::withMessages([
                    'data.status' => __('users.guards.status_invalid'),
                ]);
            }

            $submittedStatus = UserStatus::tryFrom($data['status']);

            if ($submittedStatus === null) {
                throw ValidationException::withMessages([
                    'data.status' => __('users.guards.status_invalid'),
                ]);
            }
        }

        DB::transaction(function () use (
            $record,
            $data,
            $actor,
            $requestedRoles,
            $submittedStatus,
        ): void {
            $activeSuperAdmins = User::role('super_admin')
                ->where('status', UserStatus::Active)
                ->orderBy($record->qualifyColumn($record->getKeyName()))
                ->lockForUpdate()
                ->get();

            $locked = $activeSuperAdmins->first(
                fn (User $user): bool => $user->is($record),
            );

            if ($locked === null) {
                $locked = User::query()
                    ->whereKey($record->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            if (
                $locked->status === UserStatus::Invited
                && $submittedStatus !== null
                && $submittedStatus !== UserStatus::Invited
            ) {
                throw ValidationException::withMessages([
                    'data.status' => __('users.guards.invited_status_locked'),
                ]);
            }

            $newStatus = $locked->status === UserStatus::Invited
                ? $locked->status
                : ($submittedStatus ?? $locked->status);

            if (
                $newStatus === UserStatus::Invited
                && $locked->status !== UserStatus::Invited
            ) {
                throw ValidationException::withMessages([
                    'data.status' => __('users.guards.invited_status_locked'),
                ]);
            }

            $currentRoles = $locked->getRoleNames()
                ->sort()
                ->values()
                ->all();

            $rolesChanged = $requestedRoles !== null
                && $requestedRoles !== $currentRoles;

            if ($rolesChanged && ! $actor?->can('manageRoles', $locked)) {
                throw ValidationException::withMessages([
                    'data.roles' => __('users.guards.roles_unauthorized'),
                ]);
            }

            $roleModels = null;

            if ($rolesChanged) {
                $roleModels = Role::query()
                    ->where('guard_name', config('auth.defaults.guard'))
                    ->whereIn('name', $requestedRoles)
                    ->get();

                $missingRoles = collect($requestedRoles)
                    ->diff($roleModels->pluck('name'));

                if ($missingRoles->isNotEmpty()) {
                    throw ValidationException::withMessages([
                        'data.roles' => __('users.guards.roles_unknown'),
                    ]);
                }
            }

            $targetIsLastActiveSuperAdmin =
                $activeSuperAdmins->count() === 1
                && $activeSuperAdmins->first()?->is($locked);

            if ($targetIsLastActiveSuperAdmin) {
                if ($newStatus !== UserStatus::Active) {
                    throw ValidationException::withMessages([
                        'data.status' => __(
                            'users.guards.last_super_admin_deactivate',
                        ),
                    ]);
                }

                if (
                    $rolesChanged
                    && ! in_array('super_admin', $requestedRoles, true)
                ) {
                    throw ValidationException::withMessages([
                        'data.roles' => __(
                            'users.guards.last_super_admin_role',
                        ),
                    ]);
                }
            }

            $locked->fill(Arr::only($data, ['name', 'email']));

            $locked->forceFill([
                'status' => $newStatus,
            ])->save();

            if ($rolesChanged) {
                $locked->syncRoles($roleModels);
            }
        });

        return $record->refresh();
    }
}
