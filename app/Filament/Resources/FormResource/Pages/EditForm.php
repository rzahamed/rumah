<?php

namespace App\Filament\Resources\FormResource\Pages;

use App\Filament\Resources\FormResource;
use Filament\Resources\Pages\EditRecord;

class EditForm extends EditRecord
{
    protected static string $resource = FormResource::class;

    /**
     * No delete action: the Contact form is a fixed system record, and its
     * collected submissions are additionally protected by the restrictive
     * foreign key on form_submissions.
     */
    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return FormResource::normalizeFieldDefinitions($data);
    }
}
