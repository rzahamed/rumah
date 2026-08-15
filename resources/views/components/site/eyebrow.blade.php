{{--
    Paper "Eyebrow": a 16px filled circle beside a 20px/24px label. The circle
    is drawn by .eyebrow::before in currentColor — the same construction as
    Paper's own white variant — so no image asset is involved. Pass
    class="eyebrow-inverse" on a dark surface.

    A <p> by default; sections whose eyebrow is their only heading pass
    as="h2" so the document outline stays intact (see x-home.values). The tag
    is normalized to exactly those two elements before rendering — anything
    else falls back to <p> — so the markup is always valid and no other
    element can be injected. The .eyebrow class carries the Arabic override
    that returns tracking to zero.
--}}
@props(['label', 'as' => 'p'])
@php($tag = $as === 'h2' ? 'h2' : 'p')

<{{ $tag }} {{ $attributes->merge(['class' => 'eyebrow']) }}>{{ $label }}</{{ $tag }}>
