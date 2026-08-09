<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the {locale} route segment as the active application locale for
 * public routes, rejecting any locale that isn't explicitly supported.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->route('locale', config('platform.default_locale'));

        if (! in_array($locale, config('platform.supported_locales', ['en']), true)) {
            abort(404);
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
