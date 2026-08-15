{{--
    Paper: Blog Index → "Section / Latest Blog". paddingTop 87, paddingLeft
    154 / paddingRight 190 (1096px content, centred), gap 52: the 40px/120%
    "Latest Blog" title, then a row (space-between, gap 52, top-aligned) of
    "Card / Article — Featured" (544) and "Card / Newsletter" (515).

    The featured article is the newest published post — the first item of
    page 1 — passed in by the page; when there is no post the title and the
    card are not rendered and the newsletter card stands alone, which is the
    Home page's convention for the same situation. Below desktop the two
    cards stack.
--}}
@props(['featured' => null, 'featuredUrl' => null])

<section class="pt-4xl desktop:pt-[87px]">
    <div class="container-site">
        <div class="mx-auto flex w-full max-w-[1096px] flex-col items-center gap-2xl desktop:gap-[52px]">
            @if ($featured !== null && $featuredUrl !== null)
                <h2 class="t-section-title text-center" data-animate="fade-up">{{ __('blog.index.latest_title') }}</h2>
            @endif

            <div @class([
                'flex w-full flex-col items-center gap-2xl desktop:flex-row desktop:items-start desktop:gap-[52px]',
                'desktop:justify-between' => $featured !== null && $featuredUrl !== null,
                'desktop:justify-center' => $featured === null || $featuredUrl === null,
            ])>
                @if ($featured !== null && $featuredUrl !== null)
                    <x-blog.featured-card :post="$featured" :url="$featuredUrl" />
                @endif

                <x-blog.newsletter-card />
            </div>
        </div>
    </div>
</section>
