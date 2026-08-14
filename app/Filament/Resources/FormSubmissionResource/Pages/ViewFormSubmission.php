<?php

namespace App\Filament\Resources\FormSubmissionResource\Pages;

use App\Enums\SubmissionStatus;
use App\Filament\Resources\FormSubmissionResource;
use App\Models\FormSubmission;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Gate;

/**
 * Read-only record page. The collected payload is rendered by an infolist,
 * so there is no form to submit and no field that could be dehydrated back.
 *
 * The ONE mutation available here is the triage status, behind its own
 * `updateStatus` ability — deliberately not the generic `update` ability,
 * which stays denied so no future edit path inherits authorization from it.
 *
 * Opening this page never marks a submission reviewed: triage is an
 * explicit act, never a side effect of reading.
 */
class ViewFormSubmission extends ViewRecord
{
    protected static string $resource = FormSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->statusAction(),
            DeleteAction::make(),
        ];
    }

    private function statusAction(): Action
    {
        return Action::make('updateStatus')
            ->label(__('content.submissions.update_status'))
            ->icon('heroicon-o-check-circle')
            ->modalHeading(__('content.submissions.update_status'))
            ->modalSubmitActionLabel(__('content.submissions.save_status'))
            // UI gating only — the authoritative check runs in action().
            ->visible(fn (): bool => Gate::allows('updateStatus', $this->getRecord()))
            ->schema([
                Select::make('status')
                    ->label(__('content.fields.status'))
                    ->options(SubmissionStatus::options())
                    ->default(fn (): string => $this->getRecord()->status->value)
                    ->required()
                    // Enum-backed: a forged value never reaches the model.
                    ->in(array_column(SubmissionStatus::cases(), 'value')),
            ])
            ->action(function (array $data): void {
                /** @var FormSubmission $record */
                $record = $this->getRecord();

                // Server-side and authoritative: hiding the control is not
                // authorization. Throws before anything is written.
                Gate::authorize('updateStatus', $record);

                $status = SubmissionStatus::tryFrom($data['status'] ?? '');

                if ($status === null) {
                    Notification::make()
                        ->danger()
                        ->title(__('content.submissions.status_invalid'))
                        ->send();

                    return;
                }

                /** @var User $actor */
                $actor = auth()->user();

                // applyStatus owns the review-metadata rules entirely.
                $record->applyStatus($status, $actor);

                Notification::make()
                    ->success()
                    ->title(__('content.submissions.status_updated'))
                    ->send();
            });
    }
}
