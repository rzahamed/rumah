<?php

namespace App\Filament\Resources\TeamMemberResource\Pages;

use App\Filament\Resources\TeamMemberResource;
use App\Filament\Support\CleansUpFailedUploads;
use App\Filament\Support\RecordsFreshUploads;
use App\Models\TeamMember;
use Filament\Resources\Pages\CreateRecord;

class CreateTeamMember extends CreateRecord implements RecordsFreshUploads
{
    use CleansUpFailedUploads;

    protected static string $resource = TeamMemberResource::class;

    /**
     * Page-scoped real transaction (the panel default stays off): the
     * INSERT and every model event commit or roll back together, so the
     * rollback hook below runs only when the write demonstrably failed.
     */
    protected ?bool $hasDatabaseTransactions = true;

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
