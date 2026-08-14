<?php

namespace App\Policies;

use App\Enums\UserStatus;
use App\Models\PolicyPage;
use App\Models\User;

/**
 * Policy pages are LEGAL CONTENT with a deliberately narrow surface: the two
 * canonical rows are provisioned by migration, so there is no create path,
 * and they are never deleted — editing the body and toggling publication is
 * the entire admin capability. Editors are excluded; policy text is an
 * administrator responsibility.
 *
 * Active super admins still bypass these checks via the Gate::before
 * override, which is why the Filament resource ALSO disables create and
 * delete structurally rather than relying on this policy alone.
 */
class PolicyPagePolicy
{
    public function viewAny(User $actor): bool
    {
        return $this->activeWith($actor, 'policies.view');
    }

    public function view(User $actor, PolicyPage $policy): bool
    {
        return $this->activeWith($actor, 'policies.view');
    }

    public function update(User $actor, PolicyPage $policy): bool
    {
        return $this->activeWith($actor, 'policies.update');
    }

    public function create(User $actor): bool
    {
        return false;
    }

    public function delete(User $actor, PolicyPage $policy): bool
    {
        return false;
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }

    public function forceDelete(User $actor, PolicyPage $policy): bool
    {
        return false;
    }

    public function forceDeleteAny(User $actor): bool
    {
        return false;
    }

    public function restore(User $actor, PolicyPage $policy): bool
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

    public function replicate(User $actor, PolicyPage $policy): bool
    {
        return false;
    }

    private function activeWith(User $actor, string $permission): bool
    {
        return $actor->status === UserStatus::Active
            && $actor->can($permission);
    }
}
