<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\PasswordReset\RequestPasswordReset as BaseRequestPasswordReset;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Password;

/**
 * Anti-enumeration override: the base page shows a danger notification (e.g.
 * "user not found") when the broker fails, revealing whether an account
 * exists. This page responds identically in both cases — same success
 * notification, same cleared form — while still sending mail only to real,
 * panel-authorized accounts.
 */
class RequestPasswordReset extends BaseRequestPasswordReset
{
    protected function getFailureNotification(string $status): ?Notification
    {
        // Mirror the success path's side effects exactly.
        $this->form->fill();

        return $this->getSentNotification(Password::RESET_LINK_SENT);
    }
}
