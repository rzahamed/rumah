{{--
    Paper: Home Page → "Section / Offerings". 1144px content, gap 32, centred:
    eyebrow; 40px/120% title; 20px/120% muted body max 858; the tab bar; ONE
    detail panel.

    TAB BAR — white, radius 20, the 0 2px 8 #0000001F elevation, padding 8px
    35px, tabs 36px apart. Each tab is a 24px icon and a 20px/24px label 12px
    apart with 12px vertical padding; the selected tab is a midnight-blue
    12px-radius block, padding 12px 20px, gap 16, white icon and label. Paper
    supplied glyphs for four areas and reused the "Regulatory Compliance"
    glyph for the personal/family one; those assignments are kept.

    ARCHITECTURE — five real tabs, ONE shared panel, dynamic cards. The five
    practice areas (the client's copy from the approved prototype,
    lang/home.php 'offerings.areas') are <button role="tab"> controls that
    all reference the single panel below via aria-controls. The panel is
    server-rendered once with the FIRST area's four cards; selecting another
    tab swaps only those cards' labels and photographs in place (app.js),
    updates aria-selected and the panel's aria-labelledby, resets the card
    strip to its logical start and re-measures its pager and ScrollTrigger.
    Nothing is duplicated, no anchors, no hash navigation, no page jump. The
    five label/photo sets come from ONE data source — the JSON in data-areas,
    escaped by Blade — so nothing is repeated in JavaScript.

    Without JavaScript the first area's cards stand in the panel and the
    other four tabs are visibly disabled; app.js enables them only after it
    has wired the whole pattern (Left/Right mirrored in RTL, Home/End,
    Enter/Space, roving tabindex). The bar scrolls sideways where it does not
    fit; a focused tab scrolls into view by itself. No animation marker sits
    on the tab controls or the card strip.

    PANEL — white, radius 20, the 0 2px 12 #3232321F elevation, padding
    36/47/46/47, gap 31: the 312×48 chip (Paper's shape PNG behind a white
    20px label); the intro over a 1px #E3E3E3 rule (22 apart) — the prototype
    gives one intro and one consultation paragraph shared by all five areas;
    then, 54px on, the body: a row (16px/135% muted consultation copy 450
    wide | primary CTA and an underlined "View All Services" link 17 apart),
    the four 348×386 photo cards 23 apart (radius 20, 0 2px 12 #00000066, 6px
    inset plate: #E9E9E9CC, radius 16, min 81 tall, padding 16px 20px,
    20px/120% label), and the pager: two 111×54 pills 21 apart.

    Card photographs are the client's, resources/images/home/{n}*.jpeg, whose
    numeric prefix is the tab's position; the strip is the site's scroll-snap
    strip whose pager app.js reveals when it overflows (four 348px cards in
    the 1050px panel body always do). "View All Services" resolves through
    LocalizedUrl and is absent until the Services page is routed.
--}}
@props(['calBookingUrl' => null])

@php
    $servicesUrl = \App\Support\LocalizedUrl::to(app()->getLocale(), 'services');

    // Paper's per-area glyphs, in the prototype's tab order.
    $areaIcons = ['corporate', 'regulatory', 'regulatory', 'litigation', 'ip'];

    // The client's photographs for each area's four cards, in card order.
    $areaImages = [
        ['1commercialLaw.jpeg', '1companyForma.jpeg', '1contractDraft.jpeg', '1corporateGov.jpeg'],
        ['2personalLaw.jpeg', '2inhertianceLaw.jpeg', '2laborLaw.jpeg', '2criminalLaw.jpeg'],
        ['3compliance.jpeg', '3regRev.jpeg', '3AML.jpeg', '3DataProtect.jpeg'],
        ['4courtRep.jpeg', '4dispRes.jpeg', '4comsDis.jpeg', '4arb.jpeg'],
        ['tm.jpeg', 'copyR.jpeg', '5patents.jpeg', 'ipDis.jpeg'],
    ];

    // The one data source for labels and photographs: translated labels from
    // lang, photographs resolved through Vite here. Rendered as escaped JSON
    // in data-areas for app.js and used below for the server-rendered cards.
    $areas = [];

    foreach (__('home.offerings.areas') as $index => $area) {
        $areas[] = [
            'label' => $area['label'],
            'items' => collect($area['items'])->values()->map(fn (string $label, int $i): array => [
                'label' => $label,
                'image' => Vite::asset('resources/images/home/'.$areaImages[$index][$i]),
            ])->all(),
        ];
    }

    $first = $areas[0] ?? ['label' => '', 'items' => []];
@endphp

<section class="section">
    <div class="container-site">
        <div class="mx-auto flex w-full max-w-[1144px] flex-col items-center gap-xl" data-tabs data-areas="{{ json_encode($areas, JSON_UNESCAPED_UNICODE) }}">
            <x-site.eyebrow :label="__('home.offerings.eyebrow')" data-animate="fade-up" />
            <h2 class="t-section-title text-center" data-animate="fade-up">{{ __('home.offerings.title') }}</h2>
            <p class="t-copy max-w-[858px] text-center leading-[120%] text-text-muted" data-animate="fade-up">{{ __('home.offerings.body') }}</p>

            {{-- The scroll container IS the white bar: a scroll box clips
                 everything outside its own edges, so the surface, radius
                 and elevation live on it and the list inside stays bare. --}}
            <div class="w-full overflow-x-auto rounded-card bg-surface-card shadow-soft">
                <div class="flex w-max min-w-full items-center gap-[36px] px-[35px] py-xs" role="tablist" aria-label="{{ __('home.offerings.areas_label') }}">
                    @foreach ($areas as $index => $area)
                        <button
                            class="t-copy flex shrink-0 items-center gap-sm whitespace-nowrap rounded-sm py-sm leading-6 text-text-primary transition-colors aria-selected:gap-md aria-selected:bg-background-brand aria-selected:px-[20px] aria-selected:text-text-on-brand disabled:cursor-not-allowed disabled:opacity-40"
                            id="offerings-tab-{{ $index }}"
                            type="button"
                            role="tab"
                            aria-selected="{{ $index === 0 ? 'true' : 'false' }}"
                            aria-controls="offerings-panel"
                            tabindex="{{ $index === 0 ? '0' : '-1' }}"
                            @disabled($index !== 0)
                            data-tab
                        >
                            <x-dynamic-component :component="'site.icon.'.$areaIcons[$index]" class="size-6" />
                            <span>{{ $area['label'] }}</span>
                        </button>
                    @endforeach
                </div>
            </div>

            <div
                id="offerings-panel"
                class="flex w-full flex-col items-center gap-[31px] rounded-card bg-surface-card px-lg pt-[36px] pb-[46px] shadow-panel tablet:px-[47px]"
                role="tabpanel"
                aria-labelledby="offerings-tab-0"
                data-tab-panel
            >
                <p class="relative flex h-12 w-[312px] max-w-full items-center justify-center overflow-clip rounded-sm">
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
                </p>

                <div class="flex w-full flex-col items-center gap-[22px]">
                    <p class="t-copy max-w-[772px] text-center leading-[120%] text-text-muted">{{ __('home.offerings.detail.intro') }}</p>
                    <hr class="w-full border-0 border-t border-border-hairline">
                </div>

                <div class="flex w-full flex-col gap-[54px]">
                    <div class="flex flex-col gap-lg tablet:flex-row tablet:items-center tablet:justify-between tablet:gap-4xl">
                        <p class="t-ui leading-[135%] text-text-muted tablet:w-[450px] tablet:shrink-0">{{ __('home.offerings.detail.consultation') }}</p>

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
                            {{-- The list carries the position ("1 of 4") for assistive
                                 technology; the visible numeral matches the design and
                                 is hidden from it so nothing is read twice. app.js swaps
                                 each card's label and photograph when the tab changes. --}}
                            <ol class="scroller__list" data-tab-cards>
                                @foreach ($first['items'] as $item)
                                    <li class="relative flex h-[386px] w-[348px] max-w-full flex-col justify-end overflow-clip rounded-card p-[6px] shadow-card-sm" data-scroller-item>
                                        <img
                                            class="absolute inset-0 size-full object-cover"
                                            src="{{ $item['image'] }}"
                                            alt=""
                                            loading="lazy"
                                            decoding="async"
                                            data-card-image
                                        >
                                        <p class="t-copy relative flex min-h-[81px] w-full items-center rounded-[16px] bg-surface-scrim-light px-[20px] py-md leading-[120%] text-text-primary">
                                            <span class="max-w-[286px]"><span aria-hidden="true">{{ $loop->iteration }}. </span><span data-card-label>{{ $item['label'] }}</span></span>
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
