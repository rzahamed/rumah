{{--
    Paper: Home Page → "Section / Values". A 544×544 photograph (radius 20)
    sits flush against the LEFT edge of the 1440 frame — no inline padding on
    that side — and a 623px column ends 180px from the right edge (x 637–1260).
    Paper declares gap 32 on that row but lays it out with justify-content:
    space-between, so with those two fixed widths inside the 1260px the
    resolved distance is 93px and the column is right-aligned; the same
    space-between rule reproduces exactly that here.

    The column is the eyebrow, then 56px lower a wrapping grid, 23px gaps, of
    four 300×211 cards: white, 1px --color-border-subtle (#6A6A6A80), radius
    20, the 0 2px 24 #00000066 elevation, padding 28, gap 22, a 24px/30px
    midnight-blue title over 14px/140% muted copy. The fourth card replaces
    the white with an image fill and sets its copy to primary black.

    Both images are Paper's (resources/images/home/values.png and
    values-innovation.png). CONTRAST NOTE on the fourth card: Paper crops the
    1024×1024 swoosh at 50%/50% cover, which puts the dark half of that image
    under the right-hand end of the black copy; that is reproduced as drawn
    and flagged rather than repositioned.

    The bleed is a desktop measurement, so it applies from the desktop
    breakpoint; below it the image sits inside the ordinary container above
    the column, and the grid drops to one column below tablet.

    The eyebrow is the section's heading here: this section has no separate
    title, and the cards below are h3, so an h2 is needed to keep the outline
    intact.
--}}
<section class="section">
    <div class="container-site desktop:ps-0 desktop:pe-[180px]">
        <div class="flex flex-col gap-xl desktop:flex-row desktop:items-center desktop:justify-between">
            <img
                class="aspect-square w-full max-w-[544px] shrink-0 rounded-card object-cover desktop:w-[544px]"
                data-animate="reveal-start"
                src="{{ Vite::asset('resources/images/home/values.png') }}"
                alt=""
                width="535"
                height="544"
                loading="lazy"
                decoding="async"
            >

            <div class="flex w-full flex-col desktop:w-[623px] desktop:shrink-0">
                <x-site.eyebrow as="h2" :label="__('home.values.eyebrow')" data-animate="fade-up" />

                <ul class="mt-[56px] grid gap-[23px] tablet:grid-cols-2" data-animate="stagger">
                    @foreach (__('home.values.items') as $value)
                        <li @class([
                            'relative flex min-h-[211px] flex-col gap-[22px] overflow-clip rounded-card border border-border-subtle p-[28px] shadow-card',
                            'bg-surface-card' => ! $loop->last,
                        ]) data-animate-item>
                            @if ($loop->last)
                                <img
                                    class="absolute inset-0 size-full object-cover"
                                    src="{{ Vite::asset('resources/images/home/values-innovation.png') }}"
                                    alt=""
                                    width="1024"
                                    height="1024"
                                    loading="lazy"
                                    decoding="async"
                                >
                            @endif

                            <h3 class="t-card-title relative leading-[30px] text-midnight-blue">{{ $value['title'] }}</h3>
                            <p @class(['t-caption relative leading-[140%]', 'text-text-primary' => $loop->last, 'text-text-muted' => ! $loop->last])>{{ $value['body'] }}</p>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</section>
