<?php

namespace App\Filament\Resources\PostResource\Pages;

use App\Filament\Resources\PostResource;
use App\Filament\Support\CleansUpFailedUploads;
use App\Filament\Support\RecordsFreshUploads;
use App\Models\Post;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPost extends EditRecord implements RecordsFreshUploads
{
    use CleansUpFailedUploads;

    protected static string $resource = PostResource::class;

    /**
     * Page-scoped real transaction (the panel default stays off): the
     * UPDATE and every model event commit or roll back together, and the
     * model's afterCommit old-file deletion is discarded on rollback — a
     * failed replacement can neither delete the old file nor leave the
     * fresh upload behind.
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
        // committed truth, which the reference guard reads.
        $this->cleanUpRolledBackUploads(fn (string $path): bool => Post::query()
            ->where('featured_image_path', $path)
            ->exists());
    }
}
