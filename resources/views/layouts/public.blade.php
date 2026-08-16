@php($locale = app()->getLocale())
@php($isRtl = in_array($locale, config('platform.rtl_locales', []), true))
{{--
    Resolve the page key FIRST. LocalizedUrl::to() falls back to 'home' when
    the current route is unmapped, so calling it unguarded would stamp the Home
    canonical onto every unmapped response. An unmapped route must emit neither
    a canonical nor any hreflang alternates.

    Inside the guard the key is deliberately left null: that is what lets to()
    carry the current route's own slug parameter, which passing the bare key
    back in would discard on a parameterised page such as blog.show.

    $canonicalPage is set only by a paginated listing (the blog index passes
    its current page) so page 2+ canonicalizes to itself, as LocalizedUrl's
    $page parameter exists to guarantee; everywhere else it is null and the
    URLs are the bare ones.
--}}
@php($pageKey = \App\Support\LocalizedUrl::pageKey())
@php($canonicalPage = $canonicalPage ?? null)
@php($canonicalUrl = $pageKey === null ? null : \App\Support\LocalizedUrl::to($locale, null, [], $canonicalPage))
@php($alternateUrls = $pageKey === null ? [] : \App\Support\LocalizedUrl::alternates($canonicalPage))
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    @include('public.partials.custom-code', ['snippet' => $siteSettings?->custom_head_start])
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('platform.brand_name'))</title>
    {{-- The official 64×64 favicon mark (public/favicon.ico is empty). --}}
    <link rel="icon" type="image/png" sizes="64x64" href="{{ Vite::asset('resources/images/brand/RumahLF-FV.png') }}">
    @hasSection('description')
        <meta name="description" content="@yield('description')">
    @endif
    {{-- Built from PUBLIC_APP_URL, never the Host header. --}}
    @if ($canonicalUrl !== null)
        <link rel="canonical" href="{{ $canonicalUrl }}">
    @endif
    @foreach ($alternateUrls as $hreflang => $href)
        <link rel="alternate" hreflang="{{ $hreflang }}" href="{{ $href }}">
    @endforeach
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
    @include('public.partials.custom-code', ['snippet' => $siteSettings?->custom_head_end])
</head>
<body class="bg-background-default text-text-primary">
    @include('public.partials.custom-code', ['snippet' => $siteSettings?->custom_body_start])

    <a class="skip-link" href="#main">{{ __('site.skip_to_content') }}</a>

    {{-- $calBookingUrl reaches this view from the AppServiceProvider
         composer, already validated in PHP. --}}
    <x-site.header :cal-booking-url="$calBookingUrl" />

    <main id="main" tabindex="-1">
        @yield('content')
    </main>

    <x-site.footer />

    @stack('scripts')
    @include('public.partials.custom-code', ['snippet' => $siteSettings?->custom_body_end])
</body>
</html>
