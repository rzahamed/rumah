{{--
    Paper "Icon / Arrow Right" — transcribed from resources/images/icons/arrow-right.svg
    (path data verbatim). Inline rather than <img> because the same glyph is
    drawn white on the brand pill and dark on white cards, so its stroke must
    follow the surrounding text colour; and it points along the reading
    direction, so it mirrors in Arabic (.rtl-flip).

    Decorative: the accompanying label is the accessible name, so the glyph is
    hidden from assistive technology. Size it with a class (Paper uses 16, 22
    and 28px).
--}}
<svg
    {{ $attributes->merge(['class' => 'rtl-flip shrink-0']) }}
    viewBox="0 0 16 16"
    fill="none"
    aria-hidden="true"
    focusable="false"
>
    <path d="M3.00032 8.00032H13.0003M9.25032 12.0003L13.0003 8.00032L9.25032 4.00032" stroke="currentColor" stroke-width="1.1" stroke-linecap="round" stroke-linejoin="round" />
</svg>
