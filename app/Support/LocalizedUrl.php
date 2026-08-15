<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

/**
 * Locale-equivalent URLs for the public site (language switcher, canonical
 * and hreflang tags). Host-safe by construction: every URL is built from
 * the configured PUBLIC_APP_URL — the request Host header is never
 * consulted. Only supported locales, the fixed page map below, and a
 * pattern-validated slug parameter can influence the output; query strings
 * are never carried.
 */
class LocalizedUrl
{
    /**
     * The only switchable pages, keyed by base route name. {slug} is the
     * sole recognised parameter.
     *
     * Entries exist for the pages the shipped CMS modules can produce, plus
     * this site's own static pages. They are INERT until the corresponding
     * route is registered: every method below verifies the route exists for
     * the target locale first, so an entry whose frontend has not been built
     * yet yields null rather than a URL that would 404. Adding the route is
     * all it takes to enable one.
     *
     * @var array<string, string>
     */
    private const array PATHS = [
        'home' => '/',
        'about' => '/about',
        'services' => '/services',
        'contact' => '/contact',
        'blog.index' => '/blogs',
        'blog.show' => '/blogs/{slug}',
        'policy.privacy' => '/privacy-policy',
        'policy.terms' => '/terms-of-use',
    ];

    private const string SLUG_PATTERN = '/^[a-z0-9-]+$/';

    /**
     * Base page key for the current route, or null when the route is not
     * part of the switchable map.
     */
    public static function pageKey(): ?string
    {
        $name = Route::currentRouteName();

        if ($name === null) {
            return null;
        }

        if ($name === 'home.root') {
            $name = 'home';
        } elseif (str_ends_with($name, '.localized')) {
            $name = substr($name, 0, -strlen('.localized'));
        }

        return array_key_exists($name, self::PATHS) ? $name : null;
    }

    /**
     * Absolute URL of the given page in the given locale, or null when the
     * locale is unsupported or a required parameter is missing/invalid.
     * With no explicit page, the current route (and its slug) is used,
     * falling back to the locale's home page.
     *
     * $page is the ONLY query string this class will ever emit, and it is
     * an integer by signature — callers pass a paginator's currentPage(),
     * never raw request input. Page 1 is the canonical bare URL, so only
     * 2 and above append ?page=N.
     *
     * @param  array<string, string>  $params
     */
    public static function to(string $locale, ?string $pageKey = null, array $params = [], ?int $page = null): ?string
    {
        if (! in_array($locale, config('platform.supported_locales', ['en']), true)) {
            return null;
        }

        if ($pageKey === null) {
            $pageKey = self::pageKey() ?? 'home';
            $params = self::currentParams();
        }

        $path = self::PATHS[$pageKey] ?? null;

        if ($path === null) {
            return null;
        }

        // The page must actually be routed IN THE TARGET LOCALE. The two
        // variants are separate routes, so a frontend that has built only the
        // default-locale page must not be offered a localized URL for it (or
        // the reverse).
        if (! Route::has(self::routeNameFor($pageKey, $locale))) {
            return null;
        }

        if (str_contains($path, '{slug}')) {
            $slug = $params['slug'] ?? null;

            if (! is_string($slug) || preg_match(self::SLUG_PATTERN, $slug) !== 1) {
                return null;
            }

            $path = str_replace('{slug}', $slug, $path);
        }

        $base = rtrim((string) config('platform.public_url'), '/');

        $url = $locale === config('platform.default_locale', 'en')
            ? ($path === '/' ? $base.'/' : $base.$path)
            : $base.'/'.$locale.($path === '/' ? '' : $path);

        return $page !== null && $page >= 2 ? $url.'?page='.$page : $url;
    }

    /**
     * hreflang map for the current route: every supported locale plus
     * x-default (the default locale's URL). Empty when the current route
     * is not switchable — callers then emit no alternate tags.
     *
     * $page keeps a paginated listing's canonical and alternates pointing
     * at the SAME page across locales; without it, page 2 would
     * canonicalize to page 1 and risk being dropped from the index. Every
     * entry — each supported locale AND x-default, which is taken from the
     * default locale's already-paginated URL — carries the same page. Page
     * 1 (or null) stays query-free.
     *
     * @return array<string, string>
     */
    public static function alternates(?int $page = null): array
    {
        if (self::pageKey() === null) {
            return [];
        }

        $map = [];

        foreach (config('platform.supported_locales', ['en']) as $locale) {
            $url = self::to($locale, null, [], $page);

            if ($url !== null) {
                $map[$locale] = $url;
            }
        }

        $default = (string) config('platform.default_locale', 'en');

        if (isset($map[$default])) {
            // Same string as the default locale's entry above, so it
            // carries the identical ?page=N (or none).
            $map['x-default'] = $map[$default];
        }

        return $map;
    }

    /**
     * The route name that serves a page key in a given locale.
     *
     * The default locale lives at the root path and the others behind a
     * {locale} prefix, which are separate route registrations. Home is the
     * one page whose root variant carries the distinct 'home.root' name.
     */
    private static function routeNameFor(string $pageKey, string $locale): string
    {
        $isDefault = $locale === (string) config('platform.default_locale', 'en');

        if ($pageKey === 'home') {
            return $isDefault ? 'home.root' : 'home';
        }

        return $isDefault ? $pageKey : $pageKey.'.localized';
    }

    /**
     * Recognised parameters of the current route — the slug only, taken by
     * name so the {locale} parameter can never bleed into it.
     *
     * @return array<string, string>
     */
    private static function currentParams(): array
    {
        $slug = Route::current()?->parameter('slug');

        return is_string($slug) ? ['slug' => $slug] : [];
    }
}
