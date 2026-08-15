{{--
    Paper: Blog Index → "Section / Blog Hero". paddingTop 85, paddingInline
    295 (850px content), gap 27, centred: eyebrow "All Blogs"; the 48px/120%
    title max 670; the 20px/145% muted body max 850. Copy from lang/blog.php.
--}}
<section class="pt-2xl desktop:pt-[85px]">
    <div class="container-site">
        <div class="mx-auto flex w-full max-w-[850px] flex-col items-center gap-[27px] text-center" data-animate="fade-up">
            <x-site.eyebrow :label="__('blog.index.hero.eyebrow')" />
            <h1 class="t-page-title max-w-[670px] leading-[120%]">{{ __('blog.index.hero.title') }}</h1>
            <p class="t-copy leading-[145%] text-text-muted">{{ __('blog.index.hero.body') }}</p>
        </div>
    </div>
</section>
