<?php

/*
|--------------------------------------------------------------------------
| Platform (starter) configuration
|--------------------------------------------------------------------------
|
| Generic, env-driven settings shared by every client application built from
| this starter. Nothing here is client-specific. Production swaps values via
| environment only — no code changes.
|
| Fail-closed: PUBLIC_APP_URL and ADMIN_DOMAIN are BOTH required architectural
| values with NO defaults and are validated independently (routes/web.php and
| AdminPanelProvider throw if absent). A stock APP_URL must never conceal a
| missing PUBLIC_APP_URL. Automated tests inject both via phpunit <env>.
|
*/

return [

    // Canonical PUBLIC site origin. url()/route() run inside the admin panel
    // would otherwise resolve to the admin host, so public links, the sitemap
    // and canonicals must be pinned to this value (see App\Support\PublicUrl).
    'public_url' => env('PUBLIC_APP_URL'),

    // Informational only (used in admin-facing emails/links).
    'admin_url' => env('ADMIN_APP_URL'),

    // The dedicated admin hostname. Filament is mounted here at the root path.
    // No fallback — a missing value is a configuration error.
    'admin_domain' => env('ADMIN_DOMAIN'),

    // Supported UI locales and the default.
    'supported_locales' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('SUPPORTED_LOCALES', 'en,ar'))
    ))),

    'default_locale' => env('APP_LOCALE', 'en'),

    // Locales rendered right-to-left.
    'rtl_locales' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('RTL_LOCALES', 'ar'))
    ))),

    // Brand label shown in the admin panel (client overrides via APP_NAME).
    'brand_name' => env('APP_NAME', 'CMS Starter'),

    // Media disk. Defaults to local server storage — 'public'
    // (storage/app/public, exposed via the public/storage symlink). The
    // object-storage disks defined in config/filesystems.php remain
    // available for deployments that prefer them; set MEDIA_DISK to switch
    // with no code change.
    'media_disk' => env('MEDIA_DISK', 'public'),

    // Blog featured images: same arrangement as media_disk above. Featured
    // images are public website assets.
    'blog_featured_disk' => env('BLOG_FEATURED_DISK', 'public'),

    'blog_featured_dir' => trim((string) env('BLOG_FEATURED_DIR', 'blogs'), '/'),

    // Days an admin invitation link stays valid before it expires.
    'invitation_expiry_days' => max(1, (int) env('INVITATION_EXPIRY_DAYS', 7)),

    // Hosts allowed for the Cal.com booking URL setting (exact host or any
    // subdomain of an entry). Extend for white-label Cal domains.
    'cal_allowed_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CAL_ALLOWED_HOSTS', 'cal.com'))
    ))),

    // Slug of the canonical admin-defined contact form. The form module is
    // scoped to this slug, and a frontend looks it up by the same value;
    // there is nothing to render when no active form matches.
    'contact_form_slug' => env('CONTACT_FORM_SLUG', 'contact'),

    // Posts per page for a frontend that paginates a blog listing.
    'blog_posts_per_page' => max(1, (int) env('BLOG_POSTS_PER_PAGE', 4)),

    // Cloudflare Turnstile — protects both public submission boundaries
    // (admin-defined forms and the newsletter) and the admin login.
    //
    // There is deliberately NO "enforce" flag: protection must not be
    // switchable off by a deployment variable. Verification is mandatory
    // in every environment except the explicitly listed bypass ones, and
    // App\Support\Turnstile::assertConfigured() fails closed at boot when
    // a non-bypass environment has incomplete keys — the same posture
    // PUBLIC_APP_URL and ADMIN_DOMAIN already take.
    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
        // Hardcoded, NOT env-driven: no deployment variable can add an
        // environment to this list. Keys present locally re-enable real
        // verification without any flag.
        'bypass_environments' => ['local', 'testing'],
        // BOUNDED BOTH WAYS on purpose: this timeout sits on the public
        // request path, so an oversized env value must not be able to hold
        // a submission open. Clamped to 1..10 seconds.
        'timeout' => min(10, max(1, (int) env('TURNSTILE_TIMEOUT', 5))),
    ],
];
