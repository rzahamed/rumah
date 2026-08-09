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

    // Media disk (CP4 wires GCS public/private disks; env-driven).
    'media_disk' => env('MEDIA_DISK', 'public'),

    // Days an admin invitation link stays valid before it expires.
    'invitation_expiry_days' => max(1, (int) env('INVITATION_EXPIRY_DAYS', 7)),
];
