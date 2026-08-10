<?php

namespace App\Filament\Support;

use Closure;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Filament v5 writes uploads to the FINAL disk while the form dehydrates
 * (BaseFileUpload::saveUploadedFiles, bound beforeStateDehydrated), which
 * happens BEFORE the record write; its catch blocks roll back only the
 * database. A failed write would therefore orphan the fresh upload.
 *
 * Ownership is proven by an exact-path ledger: the upload field's
 * saveUploadedFileUsing callback records the path Filament returns for each
 * TemporaryUploadedFile it stores. Plain-string state short-circuits in
 * saveUploadedFiles() before that callback, so a forged existing path can
 * never enter the ledger — only files THIS request wrote are ever eligible
 * for cleanup.
 */
trait CleansUpFailedUploads
{
    /**
     * Exact files stored by THIS Livewire request. Deliberately a private,
     * non-Livewire-synced property: the entire window from dehydration to
     * the rollback hook lies inside one synchronous page-method call, and
     * an empty ledger at the start of every request is the intended scope —
     * uploads from earlier requests are plain strings by then and never
     * re-enter the storage callback.
     *
     * @var list<array{disk: string, path: string}>
     */
    private array $freshUploads = [];

    public function recordFreshUpload(string $disk, string $path): void
    {
        $this->freshUploads[] = ['disk' => $disk, 'path' => $path];
    }

    /**
     * Runs only after a completed rollback. Deletes nothing but this
     * request's recorded uploads. Never throws — the original lifecycle
     * exception is already propagating and must never be replaced; on any
     * doubt the file is preserved.
     *
     * @param  Closure(string): bool  $referencedBy  committed-truth lookup;
     *                                               true preserves the file
     */
    protected function cleanUpRolledBackUploads(Closure $referencedBy): void
    {
        foreach ($this->freshUploads as ['disk' => $disk, 'path' => $path]) {
            try {
                // Defensive guard only — ownership was already proven by
                // the ledger. Post-rollback this reads committed truth.
                if ($referencedBy($path)) {
                    continue;
                }
            } catch (Throwable $failure) {
                report($failure);

                continue; // Reference safety unknown — preserve the file.
            }

            try {
                if (! Storage::disk($disk)->delete($path)) {
                    // Disks configured with throw => false (the local
                    // 'public' disk among them) signal failure by returning
                    // false rather than throwing. Surface it the same way —
                    // reported, never thrown, original exception preserved.
                    report(new RuntimeException(
                        "Failed to delete rolled-back upload [{$path}] on disk [{$disk}]."
                    ));
                }
            } catch (Throwable $failure) {
                report($failure);
            }
        }
    }
}
