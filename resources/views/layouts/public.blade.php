@php($locale = app()->getLocale())
@php($isRtl = in_array($locale, config('platform.rtl_locales', []), true))
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    @include('public.partials.custom-code', ['snippet' => $siteSettings?->custom_head_start])
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('platform.brand_name'))</title>
    @include('public.partials.custom-code', ['snippet' => $siteSettings?->custom_head_end])
</head>
<body>
    @include('public.partials.custom-code', ['snippet' => $siteSettings?->custom_body_start])
    @yield('content')
    @include('public.partials.custom-code', ['snippet' => $siteSettings?->custom_body_end])
</body>
</html>
