{{--
    Paper: Home Page → "Section / Offerings". 1144px content, gap 32, centred:
    eyebrow; 40px/120% title; 20px/120% muted body max 858; the tab bar; the
    detail panel.

    TAB BAR — white, radius 20, the 0 2px 8 #0000001F elevation, padding 8px
    35px, tabs 36px apart. Each tab is a 24px icon and a 20px/24px label 12px
    apart with 12px vertical padding; the selected tab (Paper names it
    "Tab / IP & Innovation Protection — selected", index 2) is a midnight-blue
    12px-radius block, padding 12px 20px, gap 16, white icon and label. Paper
    reuses the "Regulatory Compliance" glyph for the fifth tab.

    Paper supplies a detail panel for ONE practice area — the other four have
    no body copy anywhere in the design file. The bar is therefore reproduced
    as drawn, highlighted item included, but as NONINTERACTIVE list markup:
    no buttons, no links, no ARIA tab roles. Nothing here can be operated into
    an empty region. When the remaining four panels exist, this becomes a real
    tablist. Paper's bar clips its fifth tab at 1144px; here the bar scrolls
    sideways instead, and the strip is focusable and labelled so a keyboard
    can scroll it too.

    The panel copy in Paper describes litigation while the highlighted tab is
    IP & Innovation Protection. That inconsistency is the design's; because
    the intended pairing is ambiguous, the panel is deliberately not attributed
    to any one area rather than guessing.

    PANEL — white, radius 20, the 0 2px 12 #3232321F elevation, padding
    36/47/46/47, gap 31: the 312×48 "Sub Offerings" chip (Paper's shape PNG
    behind a white 20px label); a 20px/120% muted intro max 772 over a 1px
    #E3E3E3 rule (22 apart); then, 54px on, the body: a row (16px/135% muted
    consultation copy 450 wide | primary CTA and an underlined "View All
    Services" link 17 apart), the three 348×386 photo cards 23 apart (radius
    20, 0 2px 12 #00000066, 6px inset plate: #E9E9E9CC, radius 16, min 81
    tall, padding 16px 20px, 20px/120% label), and the pager: two 111×54 pills
    21 apart, white with a dark 40px arrow and midnight-blue with a white one.

    Paper's card row is 1090px inside a 1050px panel body, i.e. it overflows
    and Paper draws a pager for it. That is a scroll-snap strip here (see
    .scroller in app.css); the pager is revealed by app.js only when the strip
    actually overflows, and both strips are keyboard-focusable regions with a
    visible focus ring. The card photographs are Paper's
    (resources/images/home/offering-{1,2,3}.png). "View All Services" resolves
    through LocalizedUrl and is absent until the Services page is routed.
--}}
@props(['calBookingUrl' => null])

@php
    $servicesUrl = \App\Support\LocalizedUrl::to(app()->getLocale(), 'services');

    // Paper's per-tab glyphs, by tab index. The fifth tab reuses the fourth's.
    $areaIcons = ['corporate', 'litigation', 'ip', 'regulatory', 'regulatory'];
    $selectedArea = 2;

    $offeringImages = [
        'resources/images/home/offering-1.png',
        'resources/images/home/offering-2.png',
        'resources/images/home/offering-3.png',
    ];
@endphp

<section class="section">
    <div class="container-site">
        <div class="mx-auto flex w-full max-w-[1144px] flex-col items-center gap-xl">
            <x-site.eyebrow :label="__('home.offerings.eyebrow')" data-animate="fade-up" />
            <h2 class="t-section-title text-center" data-animate="fade-up">{{ __('home.offerings.title') }}</h2>
            <p class="t-copy max-w-[858px] text-center leading-[120%] text-text-muted" data-animate="fade-up">{{ __('home.offerings.body') }}</p>

            {{-- The scroll container IS the white bar: a scroll box clips
                 everything outside its own edges, so the surface, radius
                 and elevation live on it and the list inside stays bare. --}}
            <div class="w-full overflow-x-auto rounded-card bg-surface-card shadow-soft" role="region" aria-labelledby="offerings-areas" tabindex="0">
                <h3 id="offerings-areas" class="sr-only">{{ __('home.offerings.areas_label') }}</h3>

                <ul class="flex w-max min-w-full items-center gap-[36px] px-[35px] py-xs">
                    @foreach (__('home.offerings.areas') as $index => $area)
                        <li @class([
                            'flex shrink-0 items-center whitespace-nowrap',
                            'gap-sm py-sm text-text-primary' => $index !== $selectedArea,
                            'gap-md rounded-sm bg-background-brand px-[20px] py-sm text-text-on-brand' => $index === $selectedArea,
                        ])>
                            <x-dynamic-component :component="'site.icon.'.$areaIcons[$index]" class="size-6" />
                            <span class="t-copy leading-6">{{ $area }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="flex w-full flex-col items-center gap-[31px] rounded-card bg-surface-card px-lg pt-[36px] pb-[46px] shadow-panel tablet:px-[47px]">
                <h3 class="relative flex h-12 w-[312px] max-w-full items-center justify-center overflow-clip rounded-sm">
                    <img
                        class="absolute inset-0 size-full object-cover"
                        src="{{ Vite::asset('resources/images/home/offerings-chip.png') }}"
                        alt=""
                        width="625"
                        height="95"
                        loading="lazy"
                        decoding="async"
                    >
                    <span class="t-copy relative leading-6 text-text-on-brand">{{ __('home.offerings.detail.label') }}</span>
                </h3>

                <div class="flex w-full flex-col items-center gap-[22px]" data-animate="fade-up">
                    <p class="t-copy max-w-[772px] text-center leading-[120%] text-text-muted">{{ __('home.offerings.detail.intro') }}</p>
                    <hr class="w-full border-0 border-t border-border-hairline">
                </div>

                <div class="flex w-full flex-col gap-[54px]">
                    <div class="flex flex-col gap-lg tablet:flex-row tablet:items-center tablet:justify-between tablet:gap-4xl">
                        <p class="t-ui leading-[135%] text-text-muted tablet:w-[450px] tablet:shrink-0" data-animate="fade-up">{{ __('home.offerings.detail.consultation') }}</p>

                        @if ($calBookingUrl !== null || $servicesUrl !== null)
                            <div class="flex flex-wrap items-center gap-[17px]">
                                @if ($calBookingUrl !== null)
                                    <x-site.cta-button :href="$calBookingUrl" :label="__('site.cta.book_call')" />
                                @endif

                                @if ($servicesUrl !== null)
                                    <a class="t-ui rounded-chip px-[20px] py-xs underline decoration-1 [text-underline-position:from-font] hover:text-text-muted" href="{{ $servicesUrl }}">{{ __('site.cta.view_services') }}</a>
                                @endif
                            </div>
                        @endif
                    </div>

                    <div class="flex w-full flex-col gap-[54px]" data-scroller>
                        {{-- The strip is a scroll box, which clips at its own edges;
                             16px of padding pulled back by the same negative margin
                             keeps the cards where Paper puts them while giving their
                             0 2px 12 elevation room to render. --}}
                        <div class="scroller__track -mx-md -my-md px-md py-md" data-scroller-track role="region" aria-label="{{ __('home.offerings.detail.strip_label') }}" tabindex="0">
                            {{-- The list carries the position ("1 of 3") for assistive
                                 technology; the visible numeral matches Paper's copy
                                 and is hidden from it so nothing is read twice. --}}
                            <ol class="scroller__list">
                                @foreach (__('home.offerings.detail.items') as $item)
                                    <li class="relative flex h-[386px] w-[348px] max-w-full flex-col justify-end overflow-clip rounded-card p-[6px] shadow-card-sm" data-scroller-item>
                                        <img
                                            class="absolute inset-0 size-full object-cover"
                                            src="{{ Vite::asset($offeringImages[$loop->index]) }}"
                                            alt=""
                                            loading="lazy"
                                            decoding="async"
                                        >
                                        <p class="t-copy relative flex min-h-[81px] w-full items-center rounded-[16px] bg-surface-scrim-light px-[20px] py-md leading-[120%] text-text-primary">
                                            <span class="max-w-[286px]"><span aria-hidden="true">{{ $loop->iteration }}. </span>{{ $item }}</span>
                                        </p>
                                    </li>
                                @endforeach
                            </ol>
                        </div>

                        <div class="flex items-center justify-center gap-[21px]" data-scroller-pager hidden>
                            <button
                                class="flex h-[54px] w-[111px] items-center justify-center rounded-pill bg-surface-card text-text-primary shadow-soft transition-opacity disabled:cursor-not-allowed disabled:opacity-40"
                                type="button"
                                data-scroller-dir="prev"
                                aria-label="{{ __('home.offerings.detail.previous') }}"
                            >
                                <x-site.icon.pager-arrow direction="prev" class="size-10" />
                            </button>
                            <button
                                class="flex h-[54px] w-[111px] items-center justify-center rounded-pill bg-background-brand text-text-on-brand transition-opacity disabled:cursor-not-allowed disabled:opacity-40"
                                type="button"
                                data-scroller-dir="next"
                                aria-label="{{ __('home.offerings.detail.next') }}"
                            >
                                <x-site.icon.pager-arrow direction="next" class="size-10" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
