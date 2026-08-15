<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Auth\RequestPasswordReset;
use App\Http\Middleware\NoIndexAdmin;
use App\Http\Middleware\SetAdminLocale;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use LogicException;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        // Fail closed: the panel is only ever served on its dedicated admin
        // host at the root path. A missing ADMIN_DOMAIN is a configuration
        // error, never a silent fallback to /admin. Tests inject the value.
        $adminDomain = config('platform.admin_domain');

        if (! is_string($adminDomain) || trim($adminDomain) === '') {
            throw new LogicException(
                'ADMIN_DOMAIN must be configured. The Filament panel is only available on its dedicated admin hostname.'
            );
        }

        return $panel
            ->default()
            ->id('admin')
            ->domain($adminDomain)
            ->path('')
            // Both auth pages are subclassed rather than default: Login adds
            // the Turnstile check ahead of the credential comparison, and
            // RequestPasswordReset removes the account-enumeration signal.
            ->login(Login::class)
            ->passwordReset(RequestPasswordReset::class)
            // Fixed sidebar group order. Labels are closures so they
            // resolve AFTER SetAdminLocale per request — matching what each
            // resource's getNavigationGroup() returns in the same locale.
            ->navigationGroups([
                NavigationGroup::make(fn (): string => __('nav.groups.content')),
                NavigationGroup::make(fn (): string => __('nav.groups.forms')),
                NavigationGroup::make(fn (): string => __('nav.groups.administration')),
            ])
            ->brandName(config('platform.brand_name', config('app.name')))
            // The official logo, served through Vite like every other image;
            // Filament renders it with brandName as its alt text on the auth
            // screens and in the sidebar, at its default logo height (the
            // PNG's ratio is preserved). Closures so the manifest is read per
            // request, never at boot. The same PNG is the panel favicon.
            ->brandLogo(fn (): string => Vite::asset('resources/images/brand/Rumah-TransparentBack.png'))
            ->favicon(fn (): string => Vite::asset('resources/images/brand/Rumah-TransparentBack.png'))
            ->darkMode(false)
            ->defaultThemeMode(ThemeMode::Light)
            ->colors([
                'primary' => Color::Indigo,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn (): View => view('filament.locale-switcher'),
            )
            // Iridescent WebGL background for the AUTH pages only:
            // SIMPLE_LAYOUT_START renders solely inside the simple (auth)
            // layout template, so nothing reaches the authenticated panel.
            // The script handles prefers-reduced-motion (static frame) and
            // hides the canvas without WebGL, leaving the body gradient.
            ->renderHook(
                PanelsRenderHook::SIMPLE_LAYOUT_START,
                function (): HtmlString {
                    $css = <<<'CSS'
                    body { background: linear-gradient(160deg, #f6f7fb 0%, #eef1f8 45%, #f3eff7 100%) !important; }
                    .fi-simple-layout { background: transparent !important; position: relative; z-index: 1; min-height: 100vh; }
                    #admin-auth-bg-canvas { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; z-index: 0; pointer-events: none; }
                    .fi-simple-main-ctn { position: relative; z-index: 2; }
                    .fi-simple-main {
                        background: rgba(255, 255, 255, 0.85) !important;
                        backdrop-filter: blur(16px) !important;
                        -webkit-backdrop-filter: blur(16px) !important;
                        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 10px 30px -5px rgba(0, 0, 0, 0.08), 0 0 0 1px rgba(255, 255, 255, 0.6) !important;
                        border-radius: 1rem !important;
                    }
                    .fi-simple-layout .fi-simple-layout-header { position: relative; z-index: 2; }
                    CSS;

                    return new HtmlString(
                        '<style>'.$css.'</style>'
                        .'<canvas id="admin-auth-bg-canvas" aria-hidden="true"></canvas>'
                        .'<script src="'.e(url('/js/admin-auth-bg.js')).'" defer></script>'
                    );
                },
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                SetAdminLocale::class,
                NoIndexAdmin::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
