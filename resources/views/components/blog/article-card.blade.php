{{--
    Paper: Blog Index → "Section / Our Legal Insights" → "Card / Article".
    A 341px column, gap 24: the 341×185 thumbnail (radius 20), the 24px/120%
    title max 305, the 16px/140% muted excerpt max 291, and "Read More".

    Everything on the card is the post's own CMS data: title and excerpt via
    the model's translations, the thumbnail from featuredImageUrl(). A post
    without a featured image takes the brand surface at the same size (the
    model's documented fallback); a post without an excerpt simply has none.
    The title is a link to the post as well as the button, so the headline
    itself is operable. Width is an aspect ratio so narrow screens scale
    rather than squash.
--}}
@props(['post', 'url'])

@php($imageUrl = $post->featuredImageUrl())

<li class="flex w-full max-w-[341px] flex-col gap-lg" data-animate-item>
    @if ($imageUrl !== null)
        <img class="aspect-[341/185] w-full rounded-card object-cover" src="{{ $imageUrl }}" alt="" loading="lazy" decoding="async">
    @else
        <div class="aspect-[341/185] w-full rounded-card bg-background-brand" aria-hidden="true"></div>
    @endif

    <h3 class="t-card-title max-w-[305px] leading-[120%] text-text-primary">
        <a class="hover:text-text-muted" href="{{ $url }}">{{ $post->translate('title') }}</a>
    </h3>

    @if ($post->translate('excerpt') !== null)
        <p class="t-ui max-w-[291px] leading-[140%] text-text-muted">{{ $post->translate('excerpt') }}</p>
    @endif

    <x-blog.read-more :href="$url" :title="$post->translate('title')" />
</li>
