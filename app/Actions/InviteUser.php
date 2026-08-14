<?php

namespace App\Actions;

use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\UserInvitation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Spatie\Permission\Models\Role;

/**
 * Invites a brand-new user: the account is created in the 'invited' state
 * with an unusable random password and a single-use, expiring invitation
 * token. The user record stores only the SHA-256 token hash and its expiry;
 * the plaintext token is passed only to the queued UserInvitation
 * notification, whose payload carries it until the one-time URL is emailed.
 * Emails are normalized internally.
 *
 * Authorization is ENFORCED HERE, not assumed from the caller, so the action
 * stays safe wherever it is invoked, in two ordered steps. First, the acting
 * user must be status-active — checked on the model itself, because
 * Gate::before only grants and an inactive user could still inherit explicit
 * permissions from an assigned role. Second, the Gate must allow
 * users.create to invite or resend, plus users.manage_roles — required
 * unconditionally, because every invitation now carries exactly one role and
 * there is no roleless invite to fall through to. The active-only
 * super-admin override applies, with one explicit invite-flow rule: only an
 * ACTIVE super administrator may invite a super_admin. The requested role
 * name must exist for the configured auth guard.
 *
 * SINGLE-ROLE INVARIANT: the product model is mutually exclusive, so this
 * boundary takes ONE scalar role name. A multi-role invitation cannot be
 * expressed here, which is what keeps the invariant true for non-Filament
 * callers as well.
 */
class InviteUser
{
    /**
     * @param  string  $roleName  Exactly one role name; see the class note.
     */
    public function invite(User $invitedBy, string $name, string $email, string $roleName): User
    {
        // Ordered authorization: active status first (Gate::before only
        // grants; an inactive user could still inherit explicit role
        // permissions), then the Gate checks.
        if ($invitedBy->status !== UserStatus::Active) {
            throw new AuthorizationException('Only active users may send invitations.');
        }

        if (! $invitedBy->can('users.create')) {
            throw new AuthorizationException('You are not authorized to invite users.');
        }

        // Every invitation now carries a role, so role authority is required
        // unconditionally: there is no roleless invite to fall through to.
        if (! $invitedBy->can('users.manage_roles')) {
            throw new AuthorizationException('You are not authorized to assign roles.');
        }

        // Explicit invite-flow rule: only an active super administrator may
        // hand out super_admin through an invitation — users.manage_roles
        // alone must not let an admin mint a super admin here. Checked on
        // the RAW input with strict comparison: resolveRole() only looks the
        // name up and never renames it, so nothing can smuggle the name past
        // this point.
        if ($roleName === 'super_admin'
            && ! ($invitedBy->status === UserStatus::Active && $invitedBy->hasRole('super_admin'))) {
            throw new AuthorizationException('Only an active super administrator may invite a super administrator.');
        }

        $email = Str::lower(trim($email));
        $role = $this->resolveRole($roleName);
        [$plaintextToken, $tokenHash] = $this->freshToken();

        try {
            $user = DB::transaction(function () use ($name, $email, $role, $tokenHash): User {
                // Friendly duplicate check; the unique index stays authoritative.
                if (User::query()->where('email', $email)->exists()) {
                    throw $this->duplicateEmailValidation();
                }

                $user = new User([
                    'name' => $name,
                    'email' => $email,
                    // Unusable until acceptance sets a real one ('hashed' cast).
                    'password' => Str::random(40),
                ]);

                $user->forceFill([
                    'status' => UserStatus::Invited,
                    'invitation_token_hash' => $tokenHash,
                    'invitation_expires_at' => now()->addDays(config('platform.invitation_expiry_days')),
                ])->save();

                // syncRoles with a single-element list, never assignRole:
                // sync REPLACES, so the one-role invariant holds even if the
                // record somehow arrived with roles already attached.
                $user->syncRoles([$role]);

                return $user;
            });
        } catch (UniqueConstraintViolationException $exception) {
            // A concurrent insert won the race between the pre-check and our
            // save. Surface the SAME email-field error — but only for the
            // users.email constraint; any other violation is not ours to mask.
            if (! str_contains($exception->getMessage(), 'users_email_unique')) {
                throw $exception;
            }

            throw $this->duplicateEmailValidation();
        }

        // The notification is queued and carries afterCommit(); dispatching
        // here, after the transaction has returned, is doubly safe.
        $user->notify(new UserInvitation($plaintextToken));

        return $user;
    }

    /**
     * Renew and re-send an invitation. Only still-invited users qualify:
     * active or deactivated accounts must never receive a takeover link.
     * Writing the fresh hash over the old one structurally invalidates the
     * previously emailed URL — a single token column cannot honor two links.
     */
    public function resend(User $invitedBy, User $user): User
    {
        // Same two ordered authorization steps as invite(); no role change
        // happens here, so users.manage_roles is not required.
        if ($invitedBy->status !== UserStatus::Active) {
            throw new AuthorizationException('Only active users may send invitations.');
        }

        if (! $invitedBy->can('users.create')) {
            throw new AuthorizationException('You are not authorized to invite users.');
        }

        [$plaintextToken, $tokenHash] = $this->freshToken();

        $user = DB::transaction(function () use ($user, $tokenHash): User {
            // Row lock serializes against a concurrent acceptance of the
            // old token; the status is re-read under the lock.
            $locked = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== UserStatus::Invited) {
                throw new LogicException('Only users with a pending invitation can have it resent.');
            }

            $locked->forceFill([
                'invitation_token_hash' => $tokenHash,
                'invitation_expires_at' => now()->addDays(config('platform.invitation_expiry_days')),
            ])->save();

            return $locked;
        });

        $user->notify(new UserInvitation($plaintextToken));

        return $user;
    }

    /**
     * Validate the requested role name and return the matching Role model
     * for the configured auth guard. The value must be a non-empty string
     * that exists as a role; failures surface as validation errors attached
     * to the role input, never as raw exceptions.
     */
    private function resolveRole(string $roleName): Role
    {
        if ($roleName === '') {
            throw ValidationException::withMessages([
                'role' => 'A role is required.',
            ]);
        }

        $role = Role::query()
            ->where('guard_name', config('auth.defaults.guard'))
            ->where('name', $roleName)
            ->first();

        if ($role === null) {
            throw ValidationException::withMessages([
                'role' => 'Unknown role: '.$roleName.'.',
            ]);
        }

        return $role;
    }

    /**
     * @return array{0: string, 1: string} Plaintext token and its SHA-256 hash.
     */
    private function freshToken(): array
    {
        $plaintext = Str::random(64);

        return [$plaintext, hash('sha256', $plaintext)];
    }

    /**
     * The one shared duplicate-email error, attached to the email field and
     * identical for the pre-check and the constraint-race path.
     */
    private function duplicateEmailValidation(): ValidationException
    {
        return ValidationException::withMessages([
            'email' => __('validation.unique', ['attribute' => 'email']),
        ]);
    }
}
