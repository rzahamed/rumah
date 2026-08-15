{{--
    Paper: About Page → "Section / Our Values". paddingTop 135, 1080px
    content, centred: a 40px/120% title; 43px below it a 20px/120% muted body
    max 641; then the diagram — Paper's column gap 43 plus the diagram's own
    paddingTop 46, i.e. 89px below the body: a 434×608 portrait (radius 20)
    between two 608px-tall columns of value chips, each column an equal share
    of the remaining width with its three chips spread evenly (justify
    space-around) and hugging the portrait side; connector lines of 94, 66 and
    46px run from each chip toward the portrait. Chip anatomy lives in
    x-about.value-chip. The portrait is Paper's
    (resources/images/about/values-portrait.png), decorative, alt="".

    The three-column diagram is a desktop layout: below the desktop breakpoint
    the portrait sits above the six chips, which wrap in reading order, and
    the connector lines are not shown. The two columns are Paper's two lists
    ("Chip Column / Left" and "… / Right"), kept as two lists so their order
    matches the frame.
--}}
@php($lineWidths = [94, 66, 46])

<section class="pt-4xl desktop:pt-[135px]">
    <div class="container-site">
        <div class="mx-auto flex w-full max-w-[1080px] flex-col items-center text-center">
            <h2 class="t-section-title" data-animate="fade-up">{{ __('about.values.title') }}</h2>
            <p class="t-copy mt-xl max-w-[641px] leading-[120%] text-text-muted desktop:mt-[43px]" data-animate="fade-up">{{ __('about.values.body') }}</p>

            <div class="mt-2xl flex w-full flex-col items-center gap-lg text-start desktop:mt-[89px] desktop:flex-row desktop:justify-center desktop:gap-0">
                <ul class="order-2 flex flex-wrap justify-center gap-sm desktop:order-1 desktop:h-[608px] desktop:min-w-0 desktop:flex-1 desktop:basis-0 desktop:flex-col desktop:flex-nowrap desktop:items-end desktop:justify-around desktop:gap-0" data-animate="stagger">
                    @foreach (__('about.values.left') as $icon => $label)
                        <x-about.value-chip :label="$label" :icon="'value-'.$icon" side="left" :line="$lineWidths[$loop->index]" />
                    @endforeach
                </ul>

                <img
                    class="order-1 aspect-[434/608] w-full max-w-[434px] shrink-0 rounded-card object-cover desktop:order-2 desktop:h-[608px] desktop:w-[434px]"
                    data-animate="scale"
                    src="{{ Vite::asset('resources/images/about/values-portrait.png') }}"
                    alt=""
                    width="458"
                    height="632"
                    loading="lazy"
                    decoding="async"
                >

                <ul class="order-3 flex flex-wrap justify-center gap-sm desktop:h-[608px] desktop:min-w-0 desktop:flex-1 desktop:basis-0 desktop:flex-col desktop:flex-nowrap desktop:items-start desktop:justify-around desktop:gap-0" data-animate="stagger">
                    @foreach (__('about.values.right') as $icon => $label)
                        <x-about.value-chip :label="$label" :icon="'value-'.$icon" side="right" :line="$lineWidths[$loop->index]" />
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</section>
