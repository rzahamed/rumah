<?php

namespace Tests\Feature;

use App\Support\LocalizedUrl;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * LocalizedUrl must never emit a URL for a page the application does not
 * actually route.
 *
 * The path map carries entries for pages the CMS modules can produce, but a
 * frontend decides which of them exist. Every entry is therefore inert until
 * its route is registered — and registered SEPARATELY per locale, because the
 * default-locale page and its {locale}-prefixed variant are distinct routes.
 */
class LocalizedUrlTest extends TestCase
{
    private string $publicUrl = 'http://basecms.test';

    /**
     * Register a named route so the guard can find it.
     *
     * The name lookup table is rebuilt in an app->booted() callback, which
     * has already run by the time a test body executes, so a route named
     * after boot is invisible to Route::has() until the list is refreshed.
     */
    private function registerRoute(string $name): void
    {
        Route::get('/lu-fixture/'.md5($name), fn (): string => '')->name($name);

        Route::getRoutes()->refreshNameLookups();
    }

    // ---- Registered routes resolve --------------------------------------

    public function test_the_default_locale_home_route_resolves(): void
    {
        // Registered by the application itself as 'home.root'.
        $this->assertSame($this->publicUrl.'/', LocalizedUrl::to('en', 'home'));
    }

    public function test_a_non_default_locale_uses_the_localized_route(): void
    {
        // Registered by the application itself as 'home'.
        $this->assertSame($this->publicUrl.'/ar', LocalizedUrl::to('ar', 'home'));
    }

    // ---- Unregistered routes yield null ---------------------------------

    public function test_a_mapped_but_unrouted_page_yields_null_in_every_locale(): void
    {
        // Mapped in PATHS, but the starter registers no policy routes: the
        // entry must stay inert rather than produce a URL that would 404.
        foreach (['en', 'ar'] as $locale) {
            $this->assertNull(LocalizedUrl::to($locale, 'policy.privacy'), $locale);
            $this->assertNull(LocalizedUrl::to($locale, 'policy.terms'), $locale);
            $this->assertNull(LocalizedUrl::to($locale, 'blog.index'), $locale);
        }
    }

    public function test_a_page_routed_in_only_one_locale_resolves_in_that_locale_alone(): void
    {
        $this->registerRoute('blog.index');

        $this->assertSame($this->publicUrl.'/blogs', LocalizedUrl::to('en', 'blog.index'));
        // The localized variant was never registered.
        $this->assertNull(LocalizedUrl::to('ar', 'blog.index'));

        $this->registerRoute('blog.index.localized');

        $this->assertSame($this->publicUrl.'/ar/blogs', LocalizedUrl::to('ar', 'blog.index'));
    }

    public function test_an_unknown_page_key_yields_null(): void
    {
        $this->registerRoute('nowhere');

        // Not in PATHS: a registered route is not enough on its own.
        $this->assertNull(LocalizedUrl::to('en', 'nowhere'));
    }

    public function test_an_unsupported_locale_yields_null(): void
    {
        $this->assertNull(LocalizedUrl::to('fr', 'home'));
    }

    // ---- Parameterized routes -------------------------------------------

    public function test_a_parameterized_page_requires_a_valid_slug(): void
    {
        $this->registerRoute('blog.show');

        $this->assertSame(
            $this->publicUrl.'/blogs/hello-world',
            LocalizedUrl::to('en', 'blog.show', ['slug' => 'hello-world']),
        );

        // A missing or malformed slug must not produce a half-built path.
        $this->assertNull(LocalizedUrl::to('en', 'blog.show'));
        $this->assertNull(LocalizedUrl::to('en', 'blog.show', ['slug' => 'Bad Slug']));
        $this->assertNull(LocalizedUrl::to('en', 'blog.show', ['slug' => '../etc']));
    }

    public function test_a_parameterized_page_is_guarded_per_locale_too(): void
    {
        $this->registerRoute('blog.show');

        $this->assertNull(LocalizedUrl::to('ar', 'blog.show', ['slug' => 'hello-world']));

        $this->registerRoute('blog.show.localized');

        $this->assertSame(
            $this->publicUrl.'/ar/blogs/hello-world',
            LocalizedUrl::to('ar', 'blog.show', ['slug' => 'hello-world']),
        );
    }

    // ---- Pagination ------------------------------------------------------

    public function test_only_pages_two_and_above_carry_a_query_string(): void
    {
        $this->registerRoute('blog.index');

        $this->assertSame($this->publicUrl.'/blogs', LocalizedUrl::to('en', 'blog.index', [], 1));
        $this->assertSame($this->publicUrl.'/blogs?page=2', LocalizedUrl::to('en', 'blog.index', [], 2));
    }

    // ---- Alternates ------------------------------------------------------

    public function test_alternates_are_empty_outside_a_switchable_route(): void
    {
        // No current route matches the map, so no hreflang tags are offered.
        $this->assertSame([], LocalizedUrl::alternates());
    }
}
