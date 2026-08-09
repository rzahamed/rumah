<?php

use Filament\Facades\Filament;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Guests hitting authenticated non-panel routes (e.g. the admin
        // locale endpoint) must land on the panel's login page — there is no
        // route named 'login' in this application for the framework default
        // to fall back to. The panel is resolved explicitly by ID because
        // no current-panel context exists on custom routes.
        $middleware->redirectGuestsTo(
            fn (): ?string => Filament::getPanel('admin')->getLoginUrl(),
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
