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

    /**
     * Still denied outright. This is the ability Filament's generic edit
     * machinery consults, so granting it would authorize modifying the
     * record as a whole — including the collected payload.
     */
    public function update(User $actor, FormSubmission $submission): bool
    {
        return false;
    }

    /**
     * Allows ONLY the explicit triage-status action.
     *
     * Deliberately a custom ability rather than a reuse of update(): the
     * submitted payload stays permanently read-only, and no generic record
     * update is authorized here. Separated from submissions.view because
     * reading collected data and changing its workflow state are different
     * privileges.
     */
    public function updateStatus(User $actor, FormSubmission $submission): bool
    {
        return $this->activeWith($actor, 'submissions.update');
    }

    public function delete(User $actor, FormSubmission $submission): bool
    {
        return $this->activeWith($actor, 'submissions.delete');
    }

    /**
     * Configure who receives new-submission emails. A CLASS-level ability:
     * it concerns routing, not any one record.
     *
     * Deliberately separate from submissions.view — choosing who receives
     * collected personal data by email is a larger privilege than reading it
     * inside the panel — and from submissions.update, which is triage only.
     */
    public function manageNotifications(User $actor): bool
    {
        return $this->activeWith($actor, 'submissions.manage_notifications');
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
