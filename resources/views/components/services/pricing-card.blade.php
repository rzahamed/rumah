{{--
    Paper: Services Page → "Section / Packages" → "Card / Pricing — …". A
    345px #EEEEEE frame (radius 12, padding 6, gap 7) holding two white blocks
    (radius 12, the 0 2px 6 #0000000F elevation):

    PLAN HEADER — padding 24px 20px, gap 21: a row (gap 17) of the 26px
    midnight-blue package badge (radius 2, 0 2px 6 #00000052, 16px glyph) and
    the 20px/120% name (206 wide); then, inset 6px, the price row (gap 18): a
    20px price glyph beside a 16px/20px muted label over the 20px/24px price.

    PLAN BODY — grows to the frame's height; padding 28px 34px, gap 54,
    centred: the feature list (rows 24 apart, each a 22px check and a
    16px/125% midnight-blue line, 35px between them — Paper's 208px text
    beside a 22px glyph in the 265px row) and the primary call to action.

    Card names, prices and features are Paper's copy from lang/services.php.
    The name is an h2 beneath the section title, which is the page's h1. The
    call to action always renders and, for now, points at /contact — a
    temporary destination set by decision, not the booking URL the other
    sections use. The frame is a flex item of a wrapping row, so it keeps
    Paper's 345px at desktop and shrinks with the container below.
--}}
@props(['package'])

<li class="flex w-full max-w-[345px] flex-col gap-[7px] rounded-sm bg-surface-frame p-[6px]" data-animate-item>
    <div class="flex flex-col gap-[21px] rounded-sm bg-surface-card px-[20px] py-lg shadow-faint">
        <div class="flex items-start gap-[17px]">
            <span class="flex size-[26px] shrink-0 items-center justify-center rounded-[2px] bg-background-brand p-[5px] text-text-on-brand shadow-badge" aria-hidden="true"><x-site.icon.package class="size-4" /></span>
            <h2 class="t-copy max-w-[206px] leading-[120%] text-text-primary">{{ $package['name'] }}</h2>
        </div>

        <p class="flex items-start gap-[18px] ps-[6px]">
            <x-site.icon.price-tag class="size-5 text-text-primary" />
            <span class="flex flex-col">
                <span class="t-ui text-text-muted">{{ $package['price_label'] }}</span>
                <span class="t-copy leading-6 text-text-primary">{{ $package['price'] }}</span>
            </span>
        </p>
    </div>

    <div class="flex grow flex-col items-center gap-[54px] rounded-sm bg-surface-card px-[34px] py-[28px] shadow-faint">
        <ul class="flex w-full flex-col gap-lg">
            @foreach ($package['features'] as $feature)
                <li class="flex items-start gap-[35px]">
                    <x-site.icon.check class="size-[22px] text-midnight-blue" />
                    <span class="t-ui min-w-0 leading-[125%] text-midnight-blue">{{ $feature }}</span>
                </li>
            @endforeach
        </ul>

        <x-site.cta-button href="/contact" :label="__('site.cta.book_call')" />
    </div>
</li>
