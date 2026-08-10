<?php

namespace App\Filament\Support;

use Closure;
use Filament\Forms\Components\BaseFileUpload;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Shared saveUploadedFileUsing callback for every upload field whose page
 * cleans up rolled-back writes via CleansUpFailedUploads: vendor-default
 * storage semantics plus exact-path ownership recording.
 */
final class LedgeredUpload
{
    public static function saveUsing(): Closure
    {
        return static function (BaseFileUpload $component, TemporaryUploadedFile $file, $livewire): ?string {
            // Identical storage semantics to the vendor default, which is
            // exactly this call (BaseFileUpload::setUp).
            $path = $component->saveUploadedFile($file);

            // Ownership ledger: only TemporaryUploadedFile instances reach
            // this callback — plain-string state short-circuits in
            // saveUploadedFiles() — so $path is provably a file THIS
            // request wrote.
            if (is_string($path) && $livewire instanceof RecordsFreshUploads) {
                $livewire->recordFreshUpload($component->getDiskName(), $path);
            }

            return $path;
        };
    }
}
