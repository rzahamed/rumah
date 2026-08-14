<?php

namespace App\Policies;

use App\Enums\UserStatus;
use App\Models\User;

/**
 * Permission-gated abilities for user administration. Two structural notes:
 * (1) every ability first requires the ACTING user to be status-active,
 * because Gate::before only grants and an inactive user could still inherit
 * explicit permissions from a role; (2) active super admins bypass this
 * policy entirely via Gate::before, so the protective denials here bind
 * everyone else — UserResource re-enforces the same guards in action hooks
 * and its save path for ALL actors, super admins included.
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $this->activeWith($actor, 'users.view');
    }

    public function view(User $actor, User $target): bool
    {
        return $this->activeWith($actor, 'users.view')
            && ! $this->targetIsProtectedSuperAdmin($target);
    }

    public function create(User $actor): bool
    {
        return $this->activeWith($actor, 'users.create');
    }

    /**
     * Policies never see submitted values: status and role CHANGES are
     * validated in the UserResource save path, not here.
     */
    public function update(User $actor, User $target): bool
    {
        return $this->activeWith($actor, 'users.update')
            && ! $this->targetIsProtectedSuperAdmin($target);
    }

    /**
     * Refuses self-deletion and the last active super administrator even
     * for otherwise-authorized actors.
     */
    public function delete(User $actor, User $target): bool
    {
        return $this->activeWith($actor, 'users.delete')
            && ! $actor->is($target)
            && ! $this->targetIsProtectedSuperAdmin($target)
            && ! $target->isLastActiveSuperAdmin();
    }

    /**
     * Bulk deletion is disabled outright (fail-closed): per-record guards
     * cannot be trusted through bulk paths, so deletion happens one record
     * at a time.
     */
    public function deleteAny(User $actor): bool
    {
        return false;
    }

    // No SoftDeletes on User: force-delete and restore never apply.
    public function forceDelete(User $actor, User $target): bool
    {
        return false;
    }

    public function forceDeleteAny(User $actor): bool
    {
        return false;
    }

    public function restore(User $actor, User $target): bool
    {
        return false;
    }

    public function restoreAny(User $actor): bool
    {
        return false;
    }

    public function reorder(User $actor): bool
    {
        return false;
    }

    // Replicating an account would clone credential/lifecycle fields.
    public function replicate(User $actor, User $target): bool
    {
        return false;
    }

    /**
     * Custom ability consulted by UserResource before showing or syncing the
     * roles input. Without users.manage_roles nobody may change ANY roles —
     * their own included (self-escalation block); with it, assignment
     * follows the actor's actual authorization. $target is nullable so the
     * class-level check on the create (invite) form can use the same
     * ability.
     */
    public function manageRoles(User $actor, ?User $target = null): bool
    {
        return $this->activeWith($actor, 'users.manage_roles')
            && ! ($target !== null && $this->targetIsProtectedSuperAdmin($target));
    }

    /**
     * A super-admin target is off limits to everyone this policy still binds.
     *
     * No "unless the actor is a super admin" branch is needed: Gate::before
     * grants every ability to an ACTIVE super admin before the policy is
     * consulted, so by the time execution reaches here the actor is
     * definitionally not one. Adding that branch would duplicate the
     * override and risk the two drifting apart.
     *
     * This is the counterpart to UserResource::getEloquentQuery(): the query
     * prevents discovery, this refuses direct record operations that never
     * pass through it — a hand-typed URL or a forged Livewire call.
     */
    private function targetIsProtectedSuperAdmin(User $target): bool
    {
        return $target->hasRole('super_admin');
    }

    private function activeWith(User $actor, string $permission): bool
    {
        return $actor->status === UserStatus::Active
            && $actor->can($permission);
    }
}
