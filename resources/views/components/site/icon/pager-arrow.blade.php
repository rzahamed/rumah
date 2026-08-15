{{--
    Paper's pager glyph — transcribed from resources/images/icons/pager-prev.svg
    and pager-next.svg, which share one 40×40 up-arrow path (the two files
    differ only in stroke colour) that Paper rotates 90° to point along the
    row. Here the rotation is a prop: 'next' points forward and 'prev' points
    back, and .rtl-flip mirrors both in Arabic. Stroke follows the button's
    text colour, which is what makes one component serve the white and the
    midnight-blue button.
--}}
@props(['direction' => 'next'])

<svg
    {{ $attributes->merge(['class' => 'rtl-flip shrink-0']) }}
    viewBox="0 0 40 40"
    fill="none"
    aria-hidden="true"
    focusable="false"
>
    <g transform="rotate({{ $direction === 'prev' ? '-90' : '90' }} 20 20)">
        <path d="M20 33.3333V6.66667M10 16.6667L20 6.66667L30 16.6667" stroke="currentColor" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round" />
    </g>
</svg>
