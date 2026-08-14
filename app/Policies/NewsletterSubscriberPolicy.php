<?php

namespace App\Policies;

use App\Enums\UserStatus;
use App\Models\NewsletterSubscriber;
use App\Models\User;

/**
 * Subscribers are PROTECTED ADMIN DATA with the same deliberately narrow
 * surface as form submissions: created only by the public endpoint, never
 * editable, deletable only one at a time, and never destroyable in bulk.
 * Export is a separate ability so the right to read the list in the panel
 * and the right to carry the whole list off it can be granted apart.
 */
class NewsletterSubscriberPolicy
{
    public function viewAny(User $actor): bool
    {
        return $this->activeWith($actor, 'subscribers.view');
    }

    public function view(User $actor, NewsletterSubscriber $subscriber): bool
    {
        return $this->activeWith($actor, 'subscribers.view');
    }

    public function export(User $actor): bool
    {
        return $this->activeWith($actor, 'subscribers.export');
    }

    public function create(User $actor): bool
    {
        return false;
    }

    public function update(User $actor, NewsletterSubscriber $subscriber): bool
    {
        return false;
    }

    public function delete(User $actor, NewsletterSubscriber $subscriber): bool
    {
        return $this->activeWith($actor, 'subscribers.delete');
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }

    public function forceDelete(User $actor, NewsletterSubscriber $subscriber): bool
    {
        return false;
    }

    public function forceDeleteAny(User $actor): bool
    {
        return false;
    }

    public function restore(User $actor, NewsletterSubscriber $subscriber): bool
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

    public function replicate(User $actor, NewsletterSubscriber $subscriber): bool
    {
        return false;
    }

    private function activeWith(User $actor, string $permission): bool
    {
        return $actor->status === UserStatus::Active
            && $actor->can($permission);
    }
}
