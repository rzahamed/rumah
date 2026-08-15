{{--
    Paper "Button / Primary": a midnight-blue pill (padding 4px, 4px start,
    12px end; gap 8px) wrapping a white label pill (padding 4px 16px) followed
    by a 16px white arrow. Both pills carry the pill elevation because Paper
    draws two: the outer and the inner element each declare #00000033 0 2px 6px
    (Home hero "CTA Group", again in "Section CTA" and the Offerings panel), so
    the duplication is the design, not an accident.

    variant="on-accent" is Paper "Button / On Accent" (Final CTA card): one
    white pill, dark label, 22px dark arrow — styled by .btn-on-accent.

    The arrow points along the reading direction and mirrors in Arabic; the
    label uses .t-ui so Arabic tracking returns to zero automatically.
--}}
@props(['href', 'label', 'variant' => 'primary'])

@if ($variant === 'on-accent')
    <a href="{{ $href }}" {{ $attributes->merge(['class' => 'btn btn-on-accent']) }}>
        <span>{{ $label }}</span>
        <x-site.icon.arrow-right class="size-[22px]" />
    </a>
@else
    <a
        href="{{ $href }}"
        {{ $attributes->merge(['class' => 'inline-flex items-center gap-xs rounded-pill bg-background-brand py-2xs ps-2xs pe-sm text-text-on-brand shadow-pill transition-colors hover:bg-teal-glow']) }}
    >
        <span class="t-ui rounded-pill bg-white px-md py-2xs text-text-primary shadow-pill">{{ $label }}</span>
        <x-site.icon.arrow-right class="size-4" />
    </a>
@endif
