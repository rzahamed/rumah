<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use SensitiveParameter;

/**
 * Invitation email carrying the one-time acceptance URL. The plaintext token
 * lives only in this notification (including its queued payload) and the
 * resulting email — the user record keeps only the SHA-256 hash. Queued, and
 * held until the inviting transaction commits so a rollback can never have
 * mailed a link for a user row that does not exist.
 */
class UserInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(#[SensitiveParameter] private readonly string $plaintextToken)
    {
        $this->afterCommit();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appName = config('platform.brand_name');

        return (new MailMessage)
            ->subject(__('invitations.email.subject', ['app' => $appName]))
            ->greeting(__('invitations.email.greeting', ['name' => $notifiable->name]))
            ->line(__('invitations.email.intro', ['app' => $appName]))
            ->action(
                __('invitations.email.action'),
                route('admin.invitation.show', ['token' => $this->plaintextToken]),
            )
            ->line(__('invitations.email.expiry', ['days' => config('platform.invitation_expiry_days')]))
            ->line(__('invitations.email.ignore'));
    }
}
