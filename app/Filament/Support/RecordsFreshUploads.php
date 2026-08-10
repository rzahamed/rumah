<?php

namespace App\Filament\Support;

/**
 * Implemented by Filament pages whose upload fields record the exact paths
 * stored during the current Livewire request, so a rolled-back write can
 * clean up its own — and only its own — fresh uploads.
 */
interface RecordsFreshUploads
{
    public function recordFreshUpload(string $disk, string $path): void;
}
