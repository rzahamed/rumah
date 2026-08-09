<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * Minimal, deliberately replaceable public frontend. Each client repository
 * replaces these views/controllers with its own bespoke design.
 */
class PublicController extends Controller
{
    /** Root path → default-locale home. */
    public function root(): View
    {
        app()->setLocale(config('platform.default_locale'));

        return view('public.home');
    }

    /** Localized home (/{locale}). SetLocale middleware has already run. */
    public function home(string $locale): View
    {
        return view('public.home');
    }
}
