<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Persists the signed-in admin's preferred panel locale. State-changing, so
 * strictly POST + auth + CSRF, bound to the admin host, and validated against
 * the configured allowlist — never trusted from the client beyond that.
 */
class AdminLocaleController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in(config('platform.supported_locales', []))],
        ]);

        $request->user()
            ->forceFill(['preferred_admin_locale' => $validated['locale']])
            ->save();

        // Applied on the next admin request by SetAdminLocale middleware.
        return redirect()->back(fallback: filament()->getUrl());
    }
}
