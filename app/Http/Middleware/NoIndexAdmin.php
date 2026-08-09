<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Marks responses as non-indexable. This middleware is attached ONLY to the
 * Filament panel middleware stack (see AdminPanelProvider), so it can set the
 * header unconditionally — it never runs for public routes and makes no
 * assumption about the admin hostname.
 */
class NoIndexAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');

        return $response;
    }
}
