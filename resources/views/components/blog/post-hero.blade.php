{{--
    Paper: Blog Post Page → "Section / Post Hero". A full-width 653px row:
    "Hero Panel / Brand" (763 wide, midnight blue, 76px inline padding, gap
    44, vertically centred) holding the 42px/120% white title max 612 and a
    meta row (gap 12) — the category pill (white, 1px #75757533, radius 100,
    pill elevation, padding 7px 24px, 16px muted), a white "•", the 16px/20px
    white date — beside "Hero Panel / Media" (677 wide), the featured image
    at cover.

    Everything is the post's own CMS data. The pill and its "•" render only
    when the post has a category; a post without a featured image gives the
    media panel the brand surface (the model's documented fallback) rather
    than an invented picture. Paper also floats a white navigation pill and a
    white wordmark over this hero; the site's shared header sits above the
    hero instead, so that overlay is not reproduced (flagged). Below the
    desktop breakpoint the two panels stack: copy first, then media.
--}}
@props(['post'])

@php
    $imageUrl = $post->featuredImageUrl();
    $category = $post->category?->translate('name');
@endphp

<section class="flex w-full flex-col desktop:h-[653px] desktop:flex-row">
    <div class="flex w-full flex-col justify-center gap-[44px] bg-background-brand px-lg py-3xl text-text-on-brand tablet:px-[76px] desktop:w-[763px] desktop:shrink-0 desktop:py-0" data-animate="fade-up">
        <h1 class="t-post-title max-w-[612px]">{{ $post->translate('title') }}</h1>

        @if ($category !== null || $post->published_at !== null)
            <p class="flex flex-wrap items-center gap-sm">
                @if ($category !== null)
                    <span class="t-ui rounded-pill border border-border-tag bg-white px-lg py-[7px] text-text-muted shadow-pill">{{ $category }}</span>
                    @if ($post->published_at !== null)
                        <span class="t-ui" aria-hidden="true">•</span>
                    @endif
                @endif
                @if ($post->published_at !== null)
                    <time class="t-ui" datetime="{{ $post->published_at->toDateString() }}">{{ $post->published_at->translatedFormat('j M, Y') }}</time>
                @endif
            </p>
        @endif
    </div>

    @if ($imageUrl !== null)
        <img class="aspect-[677/653] w-full object-cover desktop:h-[653px] desktop:w-[677px] desktop:grow" data-animate="scale" src="{{ $imageUrl }}" alt="" fetchpriority="high" decoding="async">
    @else
        <div class="aspect-[677/653] w-full bg-background-brand desktop:h-[653px] desktop:w-[677px] desktop:grow" aria-hidden="true"></div>
    @endif
</section>
