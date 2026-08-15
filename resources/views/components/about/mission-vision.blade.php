{{--
    Paper: About Page → "Section / Mission & Vision". paddingTop 165, column
    gap 88: a centred head (eyebrow — Paper's label here reads "Our Values" —
    and a 40px/120% title, 32 apart), then two media/copy blocks.

    "Block / Mission": a 596×446 desert-gold band flush against the LEFT edge
    of the 1440 frame, holding the 418×419 photograph (radius 20) at its inner
    edge — 14px above, 13px below, 13px from the inner side — then 74px later
    the copy (max 641, gap 44: 32px/120% title, 20px/145% muted body); the row
    ends 129px from the right edge. "Block / Vision" mirrors it: copy first
    (right-aligned, the row starting 145px from the left edge), 70px, then a
    584×446 band flush against the RIGHT edge with its photograph at the
    inner (left) edge. Photographs are Paper's (resources/images/about/
    mission.png and vision.png), decorative, alt="".

    The bleeds are desktop measurements, so they apply from the desktop
    breakpoint; below it each band spans the ordinary container with its
    photograph centred, and copy stacks beneath (Vision keeps Paper's DOM
    order, copy then media). Alignment uses logical properties, so in Arabic
    the Mission band bleeds right and the Vision band left.
--}}
<section class="flex flex-col gap-2xl pt-4xl desktop:gap-[88px] desktop:pt-[165px]">
    <div class="container-site">
        <div class="mx-auto flex w-full flex-col items-center gap-xl text-center" data-animate="fade-up">
            <x-site.eyebrow :label="__('about.mission_vision.eyebrow')" />
            <h2 class="t-section-title">{{ __('about.mission_vision.title', ['brand' => config('platform.brand_name')]) }}</h2>
        </div>
    </div>

    {{-- Block / Mission --}}
    <div class="container-site flex flex-col items-center gap-lg desktop:flex-row desktop:gap-[74px] desktop:ps-0 desktop:pe-[129px]">
        <div class="flex w-full items-center justify-center bg-background-accent px-lg pt-[14px] pb-[13px] desktop:h-[446px] desktop:w-[596px] desktop:shrink-0 desktop:justify-end desktop:ps-0 desktop:pe-[13px]" data-animate="reveal-start">
            <img
                class="aspect-[418/419] w-full max-w-[418px] rounded-card object-cover desktop:h-[419px] desktop:w-[418px]"
                src="{{ Vite::asset('resources/images/about/mission.png') }}"
                alt=""
                width="442"
                height="443"
                loading="lazy"
                decoding="async"
            >
        </div>

        <div class="flex w-full max-w-[641px] grow flex-col gap-lg desktop:gap-[44px]" data-animate="reveal-end">
            <h3 class="t-h2">{{ __('about.mission_vision.mission.title') }}</h3>
            <p class="t-copy leading-[145%] text-text-muted">{{ __('about.mission_vision.mission.body') }}</p>
        </div>
    </div>

    {{-- Block / Vision --}}
    <div class="container-site flex flex-col items-center gap-lg desktop:flex-row desktop:gap-[70px] desktop:ps-[145px] desktop:pe-0">
        <div class="flex w-full max-w-[641px] grow flex-col gap-lg desktop:items-end desktop:gap-[44px] desktop:text-end" data-animate="reveal-start">
            <h3 class="t-h2">{{ __('about.mission_vision.vision.title') }}</h3>
            <p class="t-copy leading-[145%] text-text-muted">{{ __('about.mission_vision.vision.body') }}</p>
        </div>

        <div class="flex w-full items-center justify-center bg-background-accent px-lg pt-[14px] pb-[13px] desktop:h-[446px] desktop:w-[584px] desktop:shrink-0 desktop:justify-start desktop:ps-[13px] desktop:pe-0" data-animate="reveal-end">
            <img
                class="aspect-square w-full max-w-[418px] rounded-card object-cover desktop:size-[418px]"
                src="{{ Vite::asset('resources/images/about/vision.png') }}"
                alt=""
                width="442"
                height="442"
                loading="lazy"
                decoding="async"
            >
        </div>
    </div>
</section>
