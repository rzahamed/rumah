<?php

namespace App\Filament\Resources\PostResource\Pages;

use App\Filament\Resources\PostResource;
use App\Filament\Support\CleansUpFailedUploads;
use App\Filament\Support\RecordsFreshUploads;
use App\Models\Post;
use Filament\Resources\Pages\CreateRecord;

class CreatePost extends CreateRecord implements RecordsFreshUploads
{
    use CleansUpFailedUploads;

    protected static string $resource = PostResource::class;

    /**
     * Page-scoped real transaction (the panel default stays off): the
     * INSERT and every model event commit or roll back together, so the
     * rollback hook below runs only when the write demonstrably failed —
     * a `created` listener throwing after the SQL statement can no longer
     * leave a committed row behind.
     */
    protected ?bool $hasDatabaseTransactions = true;

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
