<?php

namespace App\Actions;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;
use Spatie\Permission\Models\Role;

/**
 * Creates the first super administrator atomically: if the role assignment
 * fails for any reason, the transaction rolls back and no orphaned active
 * user is left behind. Expects an already-normalized email.
 */
class CreateSuperAdmin
{
    public function __invoke(string $name, string $email, string $password, Role $role): User
    {
        return DB::transaction(function () use ($name, $email, $password, $role): User {
            // Lock the role row to serialize concurrent first-super-admin
            // attempts, then recheck in case the state changed while
            // interactive prompts were open.
            $lockedRole = Role::query()
                ->whereKey($role->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (User::role($lockedRole)->exists()) {
                throw new LogicException('A super administrator already exists.');
            }

            $user = new User([
                'name' => $name,
                'email' => $email,
                'password' => $password,
            ]);

            $user->forceFill([
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
            ])->save();

            $user->assignRole($lockedRole);

            return $user;
        });
    }
}
