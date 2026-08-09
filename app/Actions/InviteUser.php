<?php

namespace App\Actions;

use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\UserInvitation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
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
 * users.create to invite or resend, and additionally users.manage_roles to
 * attach any role; the active-only super-admin override applies, and the
 * assignable scope is exactly what the acting user is authorized for — with
 * one explicit invite-flow rule: only an ACTIVE super administrator may
 * include super_admin in an invitation. Every requested role name must
 * exist for the configured auth guard.
 */
class InviteUser
{
    /**
     * @param  list<string>  $roleNames
     */
    public function invite(User $invitedBy, string $name, string $email, array $roleNames = []): User
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

        if ($roleNames !== [] && ! $invitedBy->can('users.manage_roles')) {
            throw new AuthorizationException('You are not authorized to assign roles.');
        }

        // Explicit invite-flow rule: only an active super administrator may
        // hand out super_admin through an invitation — users.manage_roles
        // alone must not let an admin mint a super admin here. Checked on
        // the RAW input with strict comparison: resolveRoles() only
        // de-duplicates and never renames values, so nothing can smuggle
        // the name past this point.
        if (in_array('super_admin', $roleNames, true)
            && ! ($invitedBy->status === UserStatus::Active && $invitedBy->hasRole('super_admin'))) {
            throw new AuthorizationException('Only an active super administrator may invite a super administrator.');
        }

        $email = Str::lower(trim($email));
        $roles = $this->resolveRoles($roleNames);
        [$plaintextToken, $tokenHash] = $this->freshToken();

        try {
            $user = DB::transaction(function () use ($name, $email, $roles, $tokenHash): User {
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

                $user->syncRoles($roles);

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
     * Validate the requested role names and return the matching Role models
     * for the configured auth guard. Every supplied value must be a
     * non-empty string — nothing is silently trimmed or filtered — and every
     * name must exist as a role; failures surface as validation errors
     * attached to the roles input, never as raw exceptions.
     *
     * @param  array<array-key, mixed>  $roleNames  Raw, unvalidated input.
     * @return Collection<int, Role>
     */
    private function resolveRoles(array $roleNames): Collection
    {
        $requested = collect($roleNames);

        if ($requested->contains(fn ($value): bool => ! is_string($value) || $value === '')) {
            throw ValidationException::withMessages([
                'roles' => 'Every role must be a non-empty string.',
            ]);
        }

        $requested = $requested->unique()->values();

        $roles = Role::query()
            ->where('guard_name', config('auth.defaults.guard'))
            ->whereIn('name', $requested->all())
            ->get();

        $missing = $requested->diff($roles->pluck('name'));

        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'roles' => 'Unknown role(s): '.$missing->implode(', ').'.',
            ]);
        }

        return $roles;
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
