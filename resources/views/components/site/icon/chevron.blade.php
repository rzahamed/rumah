{{--
    Accordion chevron — transcribed from resources/images/icons/chevron.svg
    (path data verbatim). Paper draws it at 24px on every FAQ item, expanded
    or collapsed, in the same orientation, so no rotation is applied; the
    <details> element itself conveys open/closed state. Stroke follows the
    trigger's text colour. Decorative — the question is the trigger's name.
--}}
<svg
    {{ $attributes->merge(['class' => 'shrink-0']) }}
    viewBox="0 0 24 24"
    fill="none"
    aria-hidden="true"
    focusable="false"
>
    <path d="M6 9L12 15L18 9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
</svg>
