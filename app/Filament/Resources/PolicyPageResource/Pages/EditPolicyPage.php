<?php

namespace App\Filament\Resources\PolicyPageResource\Pages;

use App\Filament\Resources\PolicyPageResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPolicyPage extends EditRecord
{
    protected static string $resource = PolicyPageResource::class;

    /**
     * Publication gate: a policy may only go live once every supported
     * locale actually has body text.
     *
     * Enforced here rather than through conditional field rules so it covers
     * every save path in one place, and so it reads from the already-resolved
     * form data instead of depending on relative form-state paths. Halting
     * leaves the record exactly as it was — a blank legal page can never be
     * published by accident.
     */
    protected function beforeSave(): void
    {
        if (! ($this->data['is_published'] ?? false)) {
            return;
        }

        $body = is_array($this->data['body'] ?? null) ? $this->data['body'] : [];

        $missing = collect(config('platform.supported_locales', ['en']))
            ->reject(fn (string $locale): bool => filled($body[$locale] ?? null))
            ->map(fn (string $locale): string => strtoupper($locale))
            ->values();

        if ($missing->isEmpty()) {
            return;
        }

        Notification::make()
            ->danger()
            ->title(__('content.policies.publish_blocked_title'))
            ->body(__('content.policies.publish_blocked_body', ['locales' => $missing->implode(', ')]))
            ->persistent()
            ->send();

        $this->halt();
    }

    /**
     * No delete action: policy pages are fixed system records.
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
