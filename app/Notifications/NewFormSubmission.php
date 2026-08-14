<?php

namespace App\Notifications;

use App\Filament\Resources\FormSubmissionResource;
use App\Models\FormSubmission;
use App\Support\SubmissionPayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells configured internal recipients that a public form submission has
 * arrived.
 *
 * QUEUED and held until the creating transaction commits, so a rolled-back
 * submission can never have been announced. Because it is queued, mail
 * delivery failure does not roll back or fail the already-persisted
 * visitor submission — the public request has returned long before delivery
 * is attempted. Note this is at-least-once delivery: a worker retry after a
 * partial failure can still re-send, which is a queue property rather than
 * something this class can promise away.
 *
 * CONTENT POLICY: a concise summary plus a link. No IP address, user agent
 * or other requester metadata appears, because this application has never
 * collected any. The full message body is deliberately omitted too — the
 * email is a prompt to open the panel, not a copy of collected personal
 * data scattered across inboxes.
 */
class NewFormSubmission extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly FormSubmission $submission)
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
        // Each recipient's own admin locale, so one submission can produce
        // an English and an Arabic email from the same queued batch. Passed
        // explicitly rather than via app()->setLocale(), which on a shared
        // worker would leak into unrelated jobs.
        $locale = $notifiable->preferred_admin_locale
            ?? config('platform.default_locale', 'en');

        $payload = SubmissionPayload::for($this->submission, $locale);
        $placeholder = __('content.submissions.not_provided', [], $locale);

        return (new MailMessage)
            ->subject(__('content.submissions.email.subject', [
                'app' => config('platform.brand_name'),
            ], $locale))
            ->greeting(__('content.submissions.email.greeting', [
                'name' => $notifiable->name,
            ], $locale))
            ->line(__('content.submissions.email.intro', [], $locale))
            ->line(__('content.submissions.email.name', [
                'value' => $payload->name() ?? $placeholder,
            ], $locale))
            ->line(__('content.submissions.email.email', [
                'value' => $payload->email() ?? $placeholder,
            ], $locale))
            ->line(__('content.submissions.email.phone', [
                'value' => $payload->phone() ?? $placeholder,
            ], $locale))
            ->line(__('content.submissions.email.interest', [
                'value' => $payload->interest() ?? $placeholder,
            ], $locale))
            ->action(
                __('content.submissions.email.action', [], $locale),
                // Absolute, built from the panel's own configured admin
                // domain — never a hard-coded host.
                FormSubmissionResource::getUrl('view', ['record' => $this->submission], isAbsolute: true),
            )
            ->line(__('content.submissions.email.outro', [], $locale));
    }
}
