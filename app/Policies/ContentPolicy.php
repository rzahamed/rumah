<?php

namespace App\Policies;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared shape for content-module policies: every ability requires an
 * ACTIVE actor (Gate::before only grants, so an inactive user could still
 * inherit explicit role permissions) plus the module permission
 * "{prefix}.{verb}". Structural abilities that no content model supports
 * (soft-delete recovery, reordering, replication) are denied outright.
 * Active super admins bypass these policies entirely via Gate::before.
 */
abstract class ContentPolicy
{
    /**
     * Permission prefix, e.g. 'posts' → posts.view / posts.create / …
     */
    protected string $permissionPrefix;

    public function viewAny(User $actor): bool
    {
        return $this->activeWith($actor, 'view');
    }

    public function view(User $actor, Model $model): bool
    {
        return $this->activeWith($actor, 'view');
    }

    public function create(User $actor): bool
    {
        return $this->activeWith($actor, 'create');
    }

    public function update(User $actor, Model $model): bool
    {
        return $this->activeWith($actor, 'update');
    }

    public function delete(User $actor, Model $model): bool
    {
        return $this->activeWith($actor, 'delete');
    }

    public function deleteAny(User $actor): bool
    {
        return $this->activeWith($actor, 'delete');
    }

    public function forceDelete(User $actor, Model $model): bool
    {
        return false;
    }

    public function forceDeleteAny(User $actor): bool
    {
        return false;
    }

    public function restore(User $actor, Model $model): bool
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

    public function replicate(User $actor, Model $model): bool
    {
        return false;
    }

    protected function activeWith(User $actor, string $verb): bool
    {
        return $actor->status === UserStatus::Active
            && $actor->can($this->permissionPrefix.'.'.$verb);
    }
}
