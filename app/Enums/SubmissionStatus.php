<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Triage state of a collected form submission.
 *
 * Deliberately a workflow marker only — it records what an administrator has
 * done with a submission, never anything about the person who sent it. The
 * review metadata (reviewed_at / reviewed_by) is owned by
 * FormSubmission::applyStatus(), which is the single place transitions are
 * allowed to mutate it.
 */
enum SubmissionStatus: string implements HasColor, HasLabel
{
    case New = 'new';

    case Reviewed = 'reviewed';

    case Archived = 'archived';

    public function getLabel(): string
    {
        return __('content.submission_status.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'warning',
            self::Reviewed => 'success',
            self::Archived => 'gray',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status): array => [$status->value => $status->getLabel()])
            ->all();
    }
}
