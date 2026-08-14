<?php

namespace App\Filament\Resources\FormSubmissionResource\Pages;

use App\Filament\Resources\FormSubmissionResource;
use App\Models\FormSubmission;
use App\Models\SubmissionNotificationRecipient;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Lang;

class ListFormSubmissions extends ListRecords
{
    protected static string $resource = FormSubmissionResource::class;

    // Submissions are never created from the panel; the only header action
    // configures who is emailed when one arrives.
    protected function getHeaderActions(): array
    {
        return [
            $this->configureNotificationsAction(),
        ];
    }

    /**
     * Recipient configuration.
     *
     * Every eligible-user query is scoped to the ACTOR, so a non-super
     * administrator cannot see, search, count or preload a super admin —
     * and SubmissionNotificationRecipient::sync() re-validates every
     * submitted id server-side, rejecting the save outright rather than
     * quietly dropping an ineligible one.
     */
    private function configureNotificationsAction(): Action
    {
        return Action::make('configureNotifications')
            ->label(__('content.submissions.configure_notifications'))
            ->icon('heroicon-o-envelope')
            ->modalHeading(__('content.submissions.configure_notifications'))
            ->modalDescription(__('content.submissions.configure_notifications_hint'))
            ->modalSubmitActionLabel(__('content.submissions.save_recipients'))
            // UI gating only; action() re-authorizes.
            ->visible(fn (): bool => Gate::allows('manageNotifications', FormSubmission::class))
            ->fillForm(fn (): array => [
                'recipients' => SubmissionNotificationRecipient::selectedIdsFor(auth()->user()),
            ])
            ->schema([
                CheckboxList::make('recipients')
                    ->label(__('content.submissions.recipients'))
                    ->helperText(__('content.submissions.recipients_hint'))
                    ->options(fn (): array => SubmissionNotificationRecipient::eligibleQuery(auth()->user())
                        ->get()
                        ->mapWithKeys(fn (User $user): array => [
                            $user->getKey() => $user->name.' · '.$user->email,
                        ])
                        ->all())
                    // Role is shown as a translated label, not a made-up
                    // category: these are the application's real roles.
                    ->descriptions(fn (): array => SubmissionNotificationRecipient::eligibleQuery(auth()->user())
                        ->with('roles')
                        ->get()
                        ->mapWithKeys(fn (User $user): array => [
                            $user->getKey() => static::roleLabel($user),
                        ])
                        ->all())
                    ->searchable()
                    ->bulkToggleable()
                    ->columns(1),
            ])
            ->action(function (array $data): void {
                // Authoritative: hiding the action is not authorization.
                Gate::authorize('manageNotifications', FormSubmission::class);

                /** @var User $actor */
                $actor = auth()->user();

                // Throws ValidationException on a forged or ineligible id,
                // leaving the stored list untouched.
                $stored = SubmissionNotificationRecipient::sync(
                    $data['recipients'] ?? [],
                    $actor,
                );

                Notification::make()
                    ->success()
                    ->title(__('content.submissions.recipients_saved', ['count' => count($stored)]))
                    ->send();
            });
    }

    /** Translated label of the user's single role, or an em dash. */
    private static function roleLabel(User $user): string
    {
        $name = $user->getRoleNames()->first();

        if (! is_string($name) || $name === '') {
            return '—';
        }

        return Lang::has("users.roles.{$name}.label")
            ? __("users.roles.{$name}.label")
            : $name;
    }
}
