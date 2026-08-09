<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the admin panel's locale from the signed-in user's stored preference.
 * Safe before the column/user exists (returns null → no change).
 */
class SetAdminLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        $preferred = $user?->preferred_admin_locale;

        if ($preferred && in_array($preferred, config('platform.supported_locales', ['en']), true)) {
            app()->setLocale($preferred);
        }

        return $next($request);
    }
}
