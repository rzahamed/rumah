<?php

namespace App\Filament\Resources\PolicyPageResource\Pages;

use App\Filament\Resources\PolicyPageResource;
use Filament\Resources\Pages\ListRecords;

class ListPolicyPages extends ListRecords
{
    protected static string $resource = PolicyPageResource::class;

    // No header actions: the two policy pages are provisioned by migration
    // and are never created from the panel.
}
