<?php

namespace Tests\Feature\Content;

use App\Filament\Support\CleansUpFailedUploads;
use App\Filament\Support\RecordsFreshUploads;
use Closure;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * Trait-level guarantees of the ownership-ledger cleanup: it never throws
 * (the original lifecycle exception must remain the propagating one), it
 * reports every failure — thrown OR signalled by a false return from a
 * throw=>false disk — and it preserves the file whenever reference safety
 * cannot be confirmed. No database needed.
 */
class UploadCleanupGuardTest extends TestCase
{
    private function pageWithLedger(): object
    {
        return new class implements RecordsFreshUploads
        {
            use CleansUpFailedUploads;

            public function runCleanup(Closure $referencedBy): void
            {
                $this->cleanUpRolledBackUploads($referencedBy);
            }
        };
    }

    public function test_reference_check_failure_is_reported_and_preserves_the_file(): void
    {
        Exceptions::fake();
        Storage::fake('public');
        Storage::disk('public')->put('blogs/kept.jpg', 'bytes');

        $page = $this->pageWithLedger();
        $page->recordFreshUpload('public', 'blogs/kept.jpg');

        // Must complete without throwing: in the live flow the original
        // write exception is propagating and may not be replaced.
        $page->runCleanup(function (): never {
            throw new RuntimeException('reference lookup failed');
        });

        Storage::disk('public')->assertExists('blogs/kept.jpg');
        Exceptions::assertReported(RuntimeException::class);
    }

    public function test_storage_delete_failure_is_reported_and_not_rethrown(): void
    {
        Exceptions::fake();

        Storage::shouldReceive('disk')
            ->with('exploding')
            ->andThrow(new RuntimeException('disk unavailable'));

        $page = $this->pageWithLedger();
        $page->recordFreshUpload('exploding', 'blogs/gone.jpg');

        $page->runCleanup(fn (string $path): bool => false);

        Exceptions::assertReported(RuntimeException::class);
    }

    public function test_storage_delete_returning_false_is_reported_and_not_rethrown(): void
    {
        // Disks with throw => false (the local 'public' disk among them)
        // signal failure by RETURNING false, never throwing — the cleanup
        // must treat that as a failure too, reported without masking the
        // original lifecycle exception.
        Exceptions::fake();

        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('delete')
            ->once()
            ->with('blogs/stuck.jpg')
            ->andReturn(false);

        Storage::shouldReceive('disk')
            ->with('quiet')
            ->andReturn($disk);

        $page = $this->pageWithLedger();
        $page->recordFreshUpload('quiet', 'blogs/stuck.jpg');

        $page->runCleanup(fn (string $path): bool => false);

        Exceptions::assertReported(
            fn (RuntimeException $exception): bool => str_contains(
                $exception->getMessage(),
                'blogs/stuck.jpg',
            ),
        );
    }
}
