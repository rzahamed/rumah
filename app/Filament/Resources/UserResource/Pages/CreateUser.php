<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Actions\InviteUser;
use App\Filament\Resources\UserResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * The "create" flow is an INVITATION. All domain rules — actor
 * authorization (status-active + users.create, users.manage_roles for
 * roles), email normalization, duplicate handling, guard-scoped role
 * validation, token hashing, queued notification after commit — are
 * enforced by the InviteUser action, not re-implemented here.
 */
class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(InviteUser::class)->invite(
            auth()->user(),
            $data['name'],
            $data['email'],
            array_values($data['roles'] ?? []),
        );
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title(__('users.actions.invitation_sent'));
    }
}
