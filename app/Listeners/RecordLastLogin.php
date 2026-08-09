<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;

class RecordLastLogin
{
    /**
     * Record the authentication moment. saveQuietly: bookkeeping must not
     * trigger model events or touch updated_at semantics beyond the write.
     */
    public function handle(Login $event): void
    {
        $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
    }
}
