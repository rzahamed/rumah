{{--
    Paper: Home Page → "Section / Insights". 1257px content, gap 32, centred:
    eyebrow; 40px/120% title; 20px/120% muted body max 618. Then the grid —
    Paper's column gap 32 plus the grid's own paddingTop 45 = 77 below the
    body — three columns 30 apart, rows 18 apart:

      Column / Left     two article cards (506 tall)
      Column / Feature  one feature card (1030 tall = both rows + the 18 gap)
      Column / Right    the newsletter card (506) over one article card

    ARTICLE CARD — white, radius 20, the 0 2px 12 #00000008 elevation, padding
    4; the photograph radius 16 and 314 tall on the first left card, 305 on
    the others; then a body (pt 22, px 32, pb 24) with a 32px/120% title and a
    28px arrow at the foot ("Icon Button / Read"). FEATURE CARD — radius 20,
    photograph as its background, and at its foot (pt 32, px 26, pb 40, gap 32)
    a 32px/120% white title and "Read the full insights" at 16px beside a 28px
    white arrow ("Link / Read Full"). Each card is one link; its accessible
    name is the title (and the feature's read label), so the arrows are hidden
    from assistive technology.

    The cards are wired to the Post module — the controller supplies four
    published posts, newest first, and this component only reads them. The
    newest is the feature; the rest fill Left-top, Right-bottom, Left-bottom in
    that order so a shorter run never leaves the outer columns empty. With
    exactly four the cards take Paper's fixed places; with fewer they flow, the
    feature still spanning both rows. Photographs come from the CMS, not the
    design file: a post without a featured image shows no image, and a feature
    without one takes the brand surface. LEGIBILITY NOTE: Paper sets the
    feature's white copy straight onto the photograph with no scrim; with
    unknown CMS imagery that contrast is not guaranteed and is reproduced as
    drawn.

    The ARTICLES are withheld until the blog routes exist: each card links to
    blog.show, and LocalizedUrl returns null until that route is registered.
    Headlines that cannot be opened would be worse than none. The newsletter
    card does NOT depend on those routes and is live now, which is why the
    section is not gated as a whole; with no articles it stands alone.

    No "view all insights" link is rendered because Paper's Insights frame does
    not contain one.
--}}
@props(['posts'])

@php
    $locale = app()->getLocale();
    $indexUrl = \App\Support\LocalizedUrl::to($locale, 'blog.index');

    $articles = [];

    if ($indexUrl !== null) {
        foreach ($posts as $post) {
            $url = \App\Support\LocalizedUrl::to($locale, 'blog.show', ['slug' => $post->slug]);

            if ($url !== null) {
                $articles[] = [
                    'title' => $post->translate('title'),
                    'url' => $url,
                    // Resolved once: the accessor builds a disk URL on every
                    // call and the template needs it more than once.
                    'imageUrl' => $post->featuredImageUrl(),
                ];
            }
        }
    }

    $feature = $articles[0] ?? null;

    // Paper's slots for the remaining three, in fill order, with each slot's
    // desktop placement and photograph height.
    $slots = [
        ['article' => $articles[1] ?? null, 'place' => 'desktop:col-start-1 desktop:row-start-1', 'image' => 'h-[314px]'],
        ['article' => $articles[2] ?? null, 'place' => 'desktop:col-start-3 desktop:row-start-2', 'image' => 'h-[305px]'],
        ['article' => $articles[3] ?? null, 'place' => 'desktop:col-start-1 desktop:row-start-2', 'image' => 'h-[305px]'],
    ];
    $pinned = count($articles) === 4;
    $newsletterClass = $pinned ? 'min-h-[506px] desktop:col-start-3 desktop:row-start-1' : 'min-h-[506px]';
@endphp

<section class="section">
    <div class="container-site">
        <div class="mx-auto flex w-full max-w-[1257px] flex-col items-center gap-xl text-center" data-animate="fade-up">
            <x-site.eyebrow :label="__('home.insights.eyebrow')" />
            <h2 class="t-section-title">{{ __('home.insights.title') }}</h2>
            <p class="t-copy max-w-[618px] leading-[120%] text-text-muted">{{ __('home.insights.body') }}</p>
        </div>

        @if ($articles === [])
            <div class="mx-auto mt-xl w-full max-w-[399px] tablet:mt-[77px]">
                <x-site.newsletter-form :heading="__('home.insights.newsletter.heading')" :placeholder="__('home.insights.newsletter.placeholder')" class="min-h-[506px]" />
            </div>
        @else
            <div class="mx-auto mt-xl grid w-full max-w-[1257px] gap-[18px] tablet:mt-[77px] tablet:grid-cols-2 desktop:grid-cols-3 desktop:gap-x-[30px]" data-animate="stagger">
                <article @class(['row-span-2', 'desktop:col-start-2 desktop:row-start-1' => $pinned]) data-animate-item>
                    <a
                        @class([
                            'relative flex size-full min-h-[506px] flex-col justify-end overflow-clip rounded-card text-text-on-brand',
                            'bg-background-brand' => $feature['imageUrl'] === null,
                        ])
                        href="{{ $feature['url'] }}"
                    >
                        @if ($feature['imageUrl'] !== null)
                            <img class="absolute inset-0 size-full object-cover" src="{{ $feature['imageUrl'] }}" alt="" loading="lazy" decoding="async">
                        @endif

                        <div class="relative flex flex-col gap-xl px-[26px] pt-xl pb-[40px]">
                            <h3 class="t-h2">{{ $feature['title'] }}</h3>
                            <span class="flex items-center gap-sm">
                                <span class="t-ui">{{ __('home.insights.feature_cta') }}</span>
                                <x-site.icon.arrow-right class="size-7" />
                            </span>
                        </div>
                    </a>
                </article>

                @foreach ($slots as $slot)
                    @continue($slot['article'] === null)

                    <article @class([$slot['place'] => $pinned]) data-animate-item>
                        <a class="flex size-full min-h-[506px] flex-col overflow-clip rounded-card bg-surface-card p-2xs shadow-hairline" href="{{ $slot['article']['url'] }}">
                            @if ($slot['article']['imageUrl'] !== null)
                                <img @class(['w-full shrink-0 rounded-[16px] object-cover', $slot['image']]) src="{{ $slot['article']['imageUrl'] }}" alt="" loading="lazy" decoding="async">
                            @endif

                            <div class="flex grow flex-col justify-between gap-lg px-xl pt-[22px] pb-lg">
                                <h3 class="t-h2 text-text-primary">{{ $slot['article']['title'] }}</h3>
                                <x-site.icon.arrow-right class="size-7 text-text-primary" />
                            </div>
                        </a>
                    </article>
                @endforeach

                <x-site.newsletter-form
                    :heading="__('home.insights.newsletter.heading')"
                    :placeholder="__('home.insights.newsletter.placeholder')"
                    :class="$newsletterClass"
                />
            </div>
        @endif
    </div>
</section>
