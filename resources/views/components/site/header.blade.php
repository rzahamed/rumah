{{--
    $calBookingUrl is a PROP, already validated before it arrives. This
    component neither queries a model nor validates a URL; a null prop simply
    renders no call to action.
--}}
@props(['calBookingUrl' => null])

@php
    $locale = app()->getLocale();
    $currentKey = \App\Support\LocalizedUrl::pageKey();
    $homeUrl = \App\Support\LocalizedUrl::to($locale, 'home');
    $aboutUrl = \App\Support\LocalizedUrl::to($locale, 'about');

    // Every destination resolves through LocalizedUrl, which returns null
    // until that page is routed in this locale. An unbuilt page disappears
    // from the menu instead of becoming a dead link, and reappears by itself
    // once its route is registered.
    $navLinks = [];

    foreach ([
        'home' => __('site.nav.home'),
        'about' => __('site.nav.about'),
        'services' => __('site.nav.services'),
        'blog.index' => __('site.nav.blog'),
    ] as $key => $label) {
        $url = \App\Support\LocalizedUrl::to($locale, $key);

        if ($url !== null) {
            $navLinks[] = ['key' => $key, 'label' => $label, 'url' => $url];
        }
    }

    // Team is a section of the About page, not a page of its own: it has no
    // page key and so never marks itself current. Spliced in directly after
    // About to hold Paper's order.
    if ($aboutUrl !== null) {
        $aboutIndex = array_search('about', array_column($navLinks, 'key'), true);

        array_splice($navLinks, $aboutIndex + 1, 0, [[
            'key' => null,
            'label' => __('site.nav.team'),
            'url' => $aboutUrl.'#team',
        ]]);
    }

    // The SAME page in each other supported locale, or nothing when this route
    // has no equivalent there.
    $localeLinks = [];

    foreach (config('platform.supported_locales', ['en']) as $supported) {
        $url = $supported === $locale ? null : \App\Support\LocalizedUrl::to($supported);

        if ($url !== null) {
            $localeLinks[] = [
                'locale' => $supported,
                'url' => $url,
                'name' => __('site.language.names.'.$supported),
            ];
        }
    }
@endphp

{{-- Paper "Navigation / Desktop": paddingTop 40, paddingBottom 24, links 44px
     apart, no rule beneath. Those measurements are the desktop frame's, so
     they apply from the tablet breakpoint; the mobile bar keeps its existing
     16px vertical padding because Paper supplies no mobile navigation. --}}
<header class="bg-background-default">
    <div class="container-site relative flex items-center justify-between gap-md py-md tablet:pt-[40px] tablet:pb-lg">
        <x-site.brand :href="$homeUrl" />

        {{-- Tablet and up: the bar itself. --}}
        <div class="hidden tablet:flex tablet:items-center tablet:gap-xl">
            <nav aria-label="{{ __('site.nav.primary_label') }}">
                <x-site.nav-links :links="$navLinks" :current="$currentKey" class="flex items-center gap-[44px]" />
            </nav>

            @if ($localeLinks !== [])
                <nav aria-label="{{ __('site.language.label') }}">
                    <ul class="flex items-center gap-sm">
                        @foreach ($localeLinks as $link)
                            <li>
                                <a
                                    class="t-caption text-text-muted hover:text-text-primary"
                                    href="{{ $link['url'] }}"
                                    lang="{{ $link['locale'] }}"
                                    hreflang="{{ $link['locale'] }}"
                                    aria-label="{{ __('site.language.switch_to', ['language' => $link['name']]) }}"
                                >{{ $link['name'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                </nav>
            @endif

            @if ($calBookingUrl !== null)
                <x-site.cta-button :href="$calBookingUrl" :label="__('site.cta.book_call')" />
            @endif
        </div>

        {{-- Below tablet: a native disclosure. It opens, closes, takes focus
             and answers Enter/Space with no JavaScript at all, so navigation
             never depends on a script having loaded. --}}
        <details class="tablet:hidden">
            <summary class="btn btn-secondary list-none [&::-webkit-details-marker]:hidden">{{ __('site.nav.menu') }}</summary>

            <div class="absolute start-0 end-0 top-full z-50 flex flex-col gap-lg border-b border-border-subtle bg-background-default p-lg">
                <nav aria-label="{{ __('site.nav.primary_label') }}">
                    <x-site.nav-links :links="$navLinks" :current="$currentKey" class="flex flex-col gap-sm" />
                </nav>

                @if ($localeLinks !== [])
                    <nav aria-label="{{ __('site.language.label') }}">
                        <ul class="flex items-center gap-md">
                            @foreach ($localeLinks as $link)
                                <li>
                                    <a
                                        class="t-caption text-text-muted hover:text-text-primary"
                                        href="{{ $link['url'] }}"
                                        lang="{{ $link['locale'] }}"
                                        hreflang="{{ $link['locale'] }}"
                                        aria-label="{{ __('site.language.switch_to', ['language' => $link['name']]) }}"
                                    >{{ $link['name'] }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </nav>
                @endif

                @if ($calBookingUrl !== null)
                    <x-site.cta-button :href="$calBookingUrl" :label="__('site.cta.book_call')" />
                @endif
            </div>
        </details>
    </div>
</header>
