<?php

namespace App\Filament\Resources\TeamMemberResource\Pages;

use App\Filament\Resources\TeamMemberResource;
use App\Filament\Support\CleansUpFailedUploads;
use App\Filament\Support\RecordsFreshUploads;
use App\Models\TeamMember;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTeamMember extends EditRecord implements RecordsFreshUploads
{
    use CleansUpFailedUploads;

    protected static string $resource = TeamMemberResource::class;

    /**
     * Page-scoped real transaction (the panel default stays off): the
     * UPDATE and every model event commit or roll back together, and the
     * model's after-commit media deletions are discarded on rollback — a
     * failed replacement can neither delete the old files nor leave the
     * fresh uploads behind.
     */
    protected ?bool $hasDatabaseTransactions = true;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function rollBackDatabaseTransaction(): void
    {
        parent::rollBackDatabaseTransaction();

        // The rollback above has completed: the database now shows only
        // committed truth, which the reference guard reads — across BOTH
        // media columns.
        $this->cleanUpRolledBackUploads(fn (string $path): bool => TeamMember::query()
            ->where('photo_path', $path)
            ->orWhere('licence_image_path', $path)
            ->exists());
    }
}
