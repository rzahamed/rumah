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

    /**
     * Hydrate the scalar field from the record's single role.
     *
     * sole() is deliberate: the application invariant is exactly one role,
     * and a record violating it should fail visibly here rather than have
     * this page silently pick the first and then write that choice back on
     * the next save.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['role'] = $this->getRecord()->getRoleNames()->sole();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $record */
        $actor = auth()->user();

        $requestedRole = null;

        if (array_key_exists('role', $data)) {
            $rawRole = $data['role'];

            // Exactly one non-empty scalar. An array — including a
            // single-element one — is rejected outright rather than
            // unwrapped, so a crafted multi-role payload cannot slip
            // through by looking close enough to valid.
            if (! is_string($rawRole) || $rawRole === '') {
                throw ValidationException::withMessages([
                    'data.role' => __('users.guards.roles_invalid'),
                ]);
            }

            $requestedRole = $rawRole;
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
            $requestedRole,
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

            // sole() enforces the invariant at the write boundary too: a
            // record holding zero or multiple roles fails visibly instead of
            // this handler picking one. Choosing the first would be worse
            // than useless — if the submitted role happened to match it,
            // roleChanged would be false, syncRoles would never run, and the
            // surplus roles would silently survive the save.
            $currentRole = $locked->getRoleNames()->sole();

            $roleChanged = $requestedRole !== null
                && $requestedRole !== $currentRole;

            if ($roleChanged && ! $actor?->can('manageRoles', $locked)) {
                throw ValidationException::withMessages([
                    'data.role' => __('users.guards.roles_unauthorized'),
                ]);
            }

            $roleModel = null;

            if ($roleChanged) {
                $roleModel = Role::query()
                    ->where('guard_name', config('auth.defaults.guard'))
                    ->where('name', $requestedRole)
                    ->first();

                if ($roleModel === null) {
                    throw ValidationException::withMessages([
                        'data.role' => __('users.guards.roles_unknown'),
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

                if ($roleChanged && $requestedRole !== 'super_admin') {
                    throw ValidationException::withMessages([
                        'data.role' => __(
                            'users.guards.last_super_admin_role',
                        ),
                    ]);
                }
            }

            $locked->fill(Arr::only($data, ['name', 'email']));

            $locked->forceFill([
                'status' => $newStatus,
            ])->save();

            if ($roleChanged) {
                // sync REPLACES: the previous role is dropped, never appended to.
                $locked->syncRoles([$roleModel]);
            }
        });

        return $record->refresh();
    }
}
