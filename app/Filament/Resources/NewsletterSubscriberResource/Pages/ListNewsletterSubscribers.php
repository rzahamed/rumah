<?php

namespace App\Filament\Resources\NewsletterSubscriberResource\Pages;

use App\Filament\Resources\NewsletterSubscriberResource;
use App\Models\NewsletterSubscriber;
use App\Support\NewsletterCsvExport;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListNewsletterSubscribers extends ListRecords
{
    protected static string $resource = NewsletterSubscriberResource::class;

    /**
     * Export only — subscribers are never created from the panel.
     *
     * The action is gated on the dedicated 'export' ability, so the right
     * to read the list inside the panel and the right to carry the whole
     * list off it stay separable. The response is streamed by
     * NewsletterCsvExport; this action decides only who may trigger it.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label(__('content.subscribers.export'))
                ->authorize(fn (): bool => auth()->user()?->can('export', NewsletterSubscriber::class) ?? false)
                ->action(fn (): StreamedResponse => NewsletterCsvExport::stream()),
        ];
    }
}
