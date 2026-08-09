@php($locale = app()->getLocale())
@php($isRtl = in_array($locale, config('platform.rtl_locales', []), true))
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('platform.brand_name'))</title>
</head>
<body>
    @yield('content')
</body>
</html>
