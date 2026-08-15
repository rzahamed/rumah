{{--
    Paper: Blog Index → "Section / Latest Blog" → "Card / Article — Featured".
    A 544px column, gap 35: the 515×414 thumbnail (radius 20); a meta row
    (gap 25) of the category pill (#EDEDED, radius 100, padding 7px 24px,
    16px/20px primary), a muted "•" and the 16px/20px muted date; the
    32px/120% title max 529; the 20px/120% muted excerpt max 500; and "Read
    More".

    All of it is the post's own CMS data. The category pill and the "•" that
    follows it render only when the post has a category; the date is the
    post's published_at in Paper's "23 Nov, 2025" pattern via the app
    locale's translated month names. A post without a featured image takes
    the brand surface at the same size; a post without an excerpt has none.
--}}
@props(['post', 'url'])

@php
    $imageUrl = $post->featuredImageUrl();
    $category = $post->category?->translate('name');
@endphp

<article class="flex w-full max-w-[544px] flex-col gap-[35px]" data-animate="reveal-start">
    @if ($imageUrl !== null)
        <img class="aspect-[515/414] w-full max-w-[515px] rounded-card object-cover" src="{{ $imageUrl }}" alt="" width="515" height="414" fetchpriority="high" decoding="async">
    @else
        <div class="aspect-[515/414] w-full max-w-[515px] rounded-card bg-background-brand" aria-hidden="true"></div>
    @endif

    @if ($category !== null || $post->published_at !== null)
        <p class="flex flex-wrap items-center gap-[25px]">
            @if ($category !== null)
                <span class="t-ui rounded-pill bg-surface-tint px-lg py-[7px] text-text-primary">{{ $category }}</span>
                @if ($post->published_at !== null)
                    <span class="t-ui text-text-muted" aria-hidden="true">•</span>
                @endif
            @endif
            @if ($post->published_at !== null)
                <time class="t-ui text-text-muted" datetime="{{ $post->published_at->toDateString() }}">{{ $post->published_at->translatedFormat('j M, Y') }}</time>
            @endif
        </p>
    @endif

    <h3 class="t-h2 max-w-[529px] text-text-primary">
        <a class="hover:text-text-muted" href="{{ $url }}">{{ $post->translate('title') }}</a>
    </h3>

    @if ($post->translate('excerpt') !== null)
        <p class="t-copy max-w-[500px] leading-[120%] text-text-muted">{{ $post->translate('excerpt') }}</p>
    @endif

    <x-blog.read-more :href="$url" :title="$post->translate('title')" />
</article>
