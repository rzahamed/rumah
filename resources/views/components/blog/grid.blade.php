{{--
    Paper: Blog Index → "Section / Our Legal Insights". paddingTop 81,
    paddingLeft 175 / paddingRight 174 (1091px content, centred), gap 48:
    eyebrow; the 40px/120% title; then the grid — Paper's column gap 48 plus
    the grid's own paddingTop 52, i.e. 100px below the title — 341px cards
    wrapping at 34px gaps, three per row at desktop (341 × 3 + 34 × 2 =
    1091). Card anatomy lives in x-blog.article-card.

    Articles are the page's paginated CMS posts (minus the featured one on
    page 1). Pagination is Laravel's own — the project configures
    platform.blog_posts_per_page for this listing and LocalizedUrl carries
    ?page=N — rendered with the framework's default view (which supplies its
    own <nav> landmark and the localized Previous/Next strings) only when
    there is more than one page; Paper draws no pagination, so no design is
    invented for it (flagged). With no articles the section is not rendered.
--}}
@props(['articles', 'paginator'])

@if ($articles !== [])
    <section class="pt-4xl desktop:pt-[81px]">
        <div class="container-site">
            <div class="mx-auto flex w-full max-w-[1091px] flex-col items-center">
                <x-site.eyebrow :label="__('blog.index.insights.eyebrow')" data-animate="fade-up" />
                <h2 class="t-section-title mt-2xl text-center" data-animate="fade-up">{{ __('blog.index.insights.title') }}</h2>

                <ul class="mt-2xl flex w-full flex-wrap justify-center gap-[34px] desktop:mt-[100px]" data-animate="stagger">
                    @foreach ($articles as $article)
                        <x-blog.article-card :post="$article['post']" :url="$article['url']" />
                    @endforeach
                </ul>

                @if ($paginator->hasPages())
                    <div class="mt-2xl w-full">
                        {{ $paginator->links() }}
                    </div>
                @endif
            </div>
        </div>
    </section>
@endif
