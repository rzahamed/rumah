<?php

use App\Http\Controllers\AdminLocaleController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\PublicFormController;
use App\Http\Middleware\NoIndexAdmin;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public web routes
|--------------------------------------------------------------------------
|
| Public routes are ALWAYS bound to the exact hostname parsed from
| PUBLIC_APP_URL. The dedicated admin host is owned exclusively by the
| Filament panel (see AdminPanelProvider), so it can never fall through to
| these routes. Boot fails closed if PUBLIC_APP_URL has no valid hostname.
|
*/

$publicUrl = config('platform.public_url');
$publicHost = is_string($publicUrl) ? parse_url($publicUrl, PHP_URL_HOST) : null;

if (! is_string($publicHost) || $publicHost === '') {
    throw new LogicException('PUBLIC_APP_URL must contain a valid public hostname.');
}

$locales = implode('|', array_map(
    fn ($locale) => preg_quote($locale, '/'),
    config('platform.supported_locales', ['en'])
));

$defaultLocale = config('platform.default_locale', 'en');

$publicRoutes = function () use ($locales, $defaultLocale): void {
    Route::get('/', [PublicController::class, 'root'])->name('home.root');

    // About page, default locale. Its {locale} variant is registered below;
    // LocalizedUrl maps the pair as 'about' / 'about.localized'.
    Route::get('/about', [PublicController::class, 'aboutRoot'])->name('about');

    // Services page, default locale; {locale} variant below ('services.localized').
    Route::get('/services', [PublicController::class, 'servicesRoot'])->name('services');

    // Contact page, default locale; {locale} variant below ('contact.localized').
    Route::get('/contact', [PublicController::class, 'contactRoot'])->name('contact');

    // Blog, default locale; {locale} variants below ('blog.index.localized',
    // 'blog.show.localized'). Slug pattern matches LocalizedUrl's.
    Route::get('/blogs', [PublicController::class, 'blogIndexRoot'])->name('blog.index');
    Route::get('/blogs/{slug}', [PublicController::class, 'blogShowRoot'])
        ->where('slug', '[a-z0-9-]+')
        ->name('blog.show');

    // Canonicalize the default locale to the root path.
    Route::redirect('/'.$defaultLocale, '/', 301);

    // Public form submissions (admin-defined forms). Two variants matching
    // the site's routing model: the default locale at the root path, and a
    // localized route below (SetLocale), so validation and success messages
    // arrive in the visitor's language. Both throttled via the named
    // 'forms' limiter registered in AppServiceProvider.
    Route::post('/forms/{slug}', [PublicFormController::class, 'store'])
        ->middleware('throttle:forms')
        ->where('slug', '[a-z0-9-]+')
        ->name('public.forms.submit');

    // Newsletter signup. Same two-variant model as the form endpoint so
    // validation and success messages arrive in the visitor's language,
    // throttled via the named 'newsletter' limiter. A submission endpoint
    // only — a frontend places the form wherever it wants and posts here.
    Route::post('/newsletter', [NewsletterController::class, 'store'])
        ->middleware('throttle:newsletter')
        ->name('public.newsletter.subscribe');

    Route::prefix('{locale}')
        ->where(['locale' => $locales])
        ->middleware(SetLocale::class)
        ->group(function () {
            Route::get('/', [PublicController::class, 'home'])->name('home');

            Route::get('/about', [PublicController::class, 'about'])->name('about.localized');

            Route::get('/services', [PublicController::class, 'services'])->name('services.localized');

            Route::get('/contact', [PublicController::class, 'contact'])->name('contact.localized');

            Route::get('/blogs', [PublicController::class, 'blogIndex'])->name('blog.index.localized');
            Route::get('/blogs/{slug}', [PublicController::class, 'blogShow'])
                ->where('slug', '[a-z0-9-]+')
                ->name('blog.show.localized');

            Route::post('/forms/{slug}', [PublicFormController::class, 'store'])
                ->middleware('throttle:forms')
                ->where('slug', '[a-z0-9-]+')
                ->name('public.forms.submit.localized');

            Route::post('/newsletter', [NewsletterController::class, 'storeLocalized'])
                ->middleware('throttle:newsletter')
                ->name('public.newsletter.subscribe.localized');
        });
};

Route::domain($publicHost)->group($publicRoutes);

/*
|--------------------------------------------------------------------------
| Admin-host web routes (outside the Filament panel)
|--------------------------------------------------------------------------
|
| Non-panel routes that exist ONLY on the dedicated admin host: public
| invitation acceptance (rate limited via the named 'invitation' limiter
| registered in AppServiceProvider, noindexed) and the authenticated locale
| preference endpoint. Fails closed like the public host above; unknown
| hosts still match nothing and 404.
|
*/

$adminDomain = config('platform.admin_domain');

if (! is_string($adminDomain) || trim($adminDomain) === '') {
    throw new LogicException('ADMIN_DOMAIN must be configured. Admin-host routes require the dedicated admin hostname.');
}

Route::domain($adminDomain)
    ->middleware(NoIndexAdmin::class)
    ->group(function (): void {
        Route::get('/invitation/{token}', [InvitationController::class, 'show'])
            ->middleware('throttle:invitation')
            ->where('token', '[A-Za-z0-9]{64}')
            ->name('admin.invitation.show');

        Route::post('/invitation/{token}', [InvitationController::class, 'store'])
            ->middleware('throttle:invitation')
            ->where('token', '[A-Za-z0-9]{64}')
            ->name('admin.invitation.store');

        Route::post('/locale', AdminLocaleController::class)
            ->middleware('auth')
            ->name('admin.locale.update');
    });
