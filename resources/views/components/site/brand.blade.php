{{--
    The brand mark: the official logo, resources/images/brand/
    Rumah-TransparentBack.png (1600×598, transparent), served through Vite —
    the same asset path every other image on the site uses — and rendered at
    the two heights Paper gives the wordmark: 30px in the navigation and 58px
    in the footer, width following the PNG's own ratio (never stretched or
    cropped). `size` picks between exactly those two.

    On the footer's midnight-blue surface the logo's navy would vanish, so
    the footer variant renders it reversed to white with a CSS filter; a
    supplied light/reversed logo file should replace that filter (flagged).

    The link's accessible name is the configured brand name (APP_NAME) as the
    image's alt — the logo is the only content of the link, so the alt is
    what assistive technology hears. The text is NOT repeated beside it.
--}}
@props(['href', 'size' => 'nav'])
@php($footer = $size === 'footer')

<a
    href="{{ $href }}"
    rel="home"
    {{ $attributes->merge(['class' => 'inline-flex items-center']) }}
>
    <img
        @class(['w-auto', 'h-[30px]' => ! $footer, 'h-[58px] brightness-0 invert' => $footer])
        src="{{ Vite::asset('resources/images/brand/Rumah-TransparentBack.png') }}"
        alt="{{ config('platform.brand_name') }}"
        width="1600"
        height="598"
        decoding="async"
    >
</a>
