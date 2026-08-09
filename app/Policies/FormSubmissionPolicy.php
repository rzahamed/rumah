<?php

namespace App\Policies;

use App\Enums\UserStatus;
use App\Models\FormSubmission;
use App\Models\User;

/**
 * Submissions are PROTECTED ADMIN DATA with a deliberately narrow surface:
 * they are created only by the public endpoint (never through the panel),
 * can never be edited, and can only be deleted one at a time — bulk
 * destruction of collected data is denied outright.
 */
class FormSubmissionPolicy
{
    public function viewAny(User $actor): bool
    {
        return $this->activeWith($actor, 'submissions.view');
    }

    public function view(User $actor, FormSubmission $submission): bool
    {
        return $this->activeWith($actor, 'submissions.view');
    }

    public function create(User $actor): bool
    {
        return false;
    }

    public function update(User $actor, FormSubmission $submission): bool
    {
        return false;
    }

    public function delete(User $actor, FormSubmission $submission): bool
    {
        return $this->activeWith($actor, 'submissions.delete');
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }

    public function forceDelete(User $actor, FormSubmission $submission): bool
    {
        return false;
    }

    public function forceDeleteAny(User $actor): bool
    {
        return false;
    }

    public function restore(User $actor, FormSubmission $submission): bool
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

    public function replicate(User $actor, FormSubmission $submission): bool
    {
        return false;
    }

    private function activeWith(User $actor, string $permission): bool
    {
        return $actor->status === UserStatus::Active
            && $actor->can($permission);
    }
}
