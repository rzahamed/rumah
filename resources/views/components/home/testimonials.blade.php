{{--
    Paper: Home Page → "Section / Testimonials". The dark section surface,
    paddingTop 98 / paddingBottom 105, gap 32, centred: the white eyebrow
    ("Client Testimonials"), the 40px/120% white title max 600, the 20px/120%
    #C6D2DB body max 618; then the marquee — Paper's column gap 32 plus the
    marquee's own paddingTop 53, i.e. 85px below the body — two rows 18px
    apart, cards 19px apart, the second row offset. "Card / Testimonial":
    489×231, white, radius 12, padding 36px 32px, the 16px/150% primary quote
    top and the attribution row bottom (20px/24px midnight-blue name,
    16px/20px muted date, space-between).

    Content is the client's, from the approved prototype (lang/home.php,
    'testimonials'): nine cards, rendered as rows of five and four. Each row
    is a marquee (see .marquee in app.css): an <ul> of cards followed by an
    aria-hidden clone of the same cards, so assistive technology reads the
    list exactly once. STATIC BY DEFAULT: nothing moves and no clone shows
    until app.js has wired the Pause/Play button and marked the section
    ready — until then (and under reduced motion) the rows are ordinary
    scrollable strips and every card is reachable. The button is not drawn in
    Paper; it is the pause control required for content that moves on its
    own. A card whose date the client did not supply simply shows no date.
--}}
@php
    $items = __('home.testimonials.items');
    $rows = [array_slice($items, 0, 5), array_slice($items, 5)];
@endphp

<section class="marquee bg-surface-section-dark pt-4xl pb-4xl text-text-on-brand desktop:pt-[98px] desktop:pb-[105px]" data-marquee>
    <div class="container-site">
        <div class="mx-auto flex w-full max-w-[1080px] flex-col items-center gap-xl text-center" data-animate="fade-up">
            <x-site.eyebrow class="eyebrow-inverse" :label="__('home.testimonials.eyebrow')" />
            <h2 class="t-section-title max-w-[600px] text-text-on-brand">{{ __('home.testimonials.title') }}</h2>
            <p class="t-copy max-w-[618px] leading-[120%] text-text-on-brand-muted">{{ __('home.testimonials.body') }}</p>
        </div>
    </div>

    <div class="mt-xl flex flex-col gap-[18px] desktop:mt-[85px]">
        @foreach ($rows as $row)
            @continue($row === [])

            <div class="marquee__row">
                <div @class(['marquee__track', 'marquee__track--offset' => $loop->index === 1])>
                    @foreach ([false, true] as $isClone)
                        <ul @class(['marquee__group', 'marquee__clone' => $isClone]) @if ($isClone) aria-hidden="true" @else aria-label="{{ __('home.testimonials.list_label') }}" @endif>
                            @foreach ($row as $item)
                                <li class="flex w-[min(489px,85vw)] min-h-[231px] shrink-0 flex-col rounded-sm bg-white px-xl py-[36px] text-text-primary">
                                    <figure class="flex grow flex-col justify-between gap-lg">
                                        <blockquote>
                                            <p class="t-ui leading-[150%] text-text-primary">{{ $item['quote'] }}</p>
                                        </blockquote>
                                        <figcaption class="flex items-center justify-between gap-md">
                                            <span class="t-copy leading-6 text-midnight-blue">{{ $item['name'] }}</span>
                                            @if (! empty($item['date']))
                                                <span class="t-ui shrink-0 text-text-muted">{{ $item['date'] }}</span>
                                            @endif
                                        </figcaption>
                                    </figure>
                                </li>
                            @endforeach
                        </ul>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    <div class="container-site mt-lg flex justify-center">
        <button
            class="marquee__toggle t-ui inline-flex items-center rounded-pill border border-text-on-brand-muted px-[20px] py-xs text-text-on-brand transition-colors hover:bg-white hover:text-text-primary"
            type="button"
            hidden
            data-marquee-toggle
            data-label-pause="{{ __('home.testimonials.pause') }}"
            data-label-play="{{ __('home.testimonials.play') }}"
        >{{ __('home.testimonials.pause') }}</button>
    </div>
</section>
