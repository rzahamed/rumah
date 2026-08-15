{{--
    Paper: About Page → "Section / Our Values" → "Value Connector / …". One
    value of the diagram: a midnight-blue pill (padding 4px, the pill
    elevation, gap 8) wrapping a white 16px label pill and a 20px glyph, plus
    a 1px midnight-blue connector line toward the portrait. Paper draws the
    glyph on the portrait side: label-then-glyph with 12px end padding for the
    left column, glyph-then-label with 12px start padding for the right
    column — `side` picks the arrangement, and `line` is Paper's connector
    length (94, 66 or 46). Neither pill nor line may shrink or wrap: Paper's
    geometry is fixed, so both are shrink-0 and the label is nowrap.

    Static content, not a control: the chip shares Paper's primary-button
    geometry (see x-site.cta-button) but is a list item, so it is not the
    shared button component. Logical properties and DOM order make the whole
    connector mirror in Arabic without further rules. The connector is
    decorative and shown only where the three-column diagram is laid out.
--}}
@props(['label', 'icon', 'side' => 'left', 'line' => 94])
@php
    $isLeft = $side !== 'right';
    $lineWidth = in_array((int) $line, [94, 66, 46], true) ? (int) $line : 94;
@endphp

<li class="flex items-center" data-animate-item>
    @unless ($isLeft)
        <span @class(['hidden h-px shrink-0 bg-midnight-blue desktop:block', 'w-[94px]' => $lineWidth === 94, 'w-[66px]' => $lineWidth === 66, 'w-[46px]' => $lineWidth === 46]) aria-hidden="true"></span>
    @endunless

    <span @class([
        'inline-flex shrink-0 items-center gap-xs whitespace-nowrap rounded-pill bg-background-brand py-2xs text-text-on-brand shadow-pill',
        'ps-2xs pe-sm' => $isLeft,
        'ps-sm pe-2xs' => ! $isLeft,
    ])>
        @unless ($isLeft)
            <x-dynamic-component :component="'site.icon.'.$icon" class="size-5" />
        @endunless

        <span class="t-ui whitespace-nowrap rounded-pill bg-white px-md py-2xs text-text-primary shadow-pill">{{ $label }}</span>

        @if ($isLeft)
            <x-dynamic-component :component="'site.icon.'.$icon" class="size-5" />
        @endif
    </span>

    @if ($isLeft)
        <span @class(['hidden h-px shrink-0 bg-midnight-blue desktop:block', 'w-[94px]' => $lineWidth === 94, 'w-[66px]' => $lineWidth === 66, 'w-[46px]' => $lineWidth === 46]) aria-hidden="true"></span>
    @endif
</li>
