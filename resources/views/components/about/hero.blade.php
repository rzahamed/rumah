{{--
    Paper: About Page → "Section / About Hero". paddingTop 60, paddingInline
    143/142 (1155px content), gap 63: a 48px/120% centred title max 1011,
    then the photograph — full content width × 569, radius 20. No eyebrow, no
    buttons. The photograph is Paper's (resources/images/about/hero.png); it
    is illustrative, so its alt is empty, and as the largest above-the-fold
    asset it carries fetchpriority.

    The 60px top is the desktop frame's; below desktop the section keeps the
    site's ordinary 48px hero top used on Home.
--}}
<section class="pt-2xl desktop:pt-[60px]">
    <div class="container-site">
        <div class="mx-auto flex w-full max-w-[1155px] flex-col items-center gap-xl text-center desktop:gap-[63px]">
            <h1 class="t-page-title max-w-[1011px] leading-[120%]" data-animate="fade-up">{{ __('about.hero.title') }}</h1>

            <img
                class="aspect-[1155/569] w-full rounded-card object-cover"
                data-animate="scale"
                src="{{ Vite::asset('resources/images/about/hero.png') }}"
                alt=""
                width="1179"
                height="593"
                fetchpriority="high"
                decoding="async"
            >
        </div>
    </div>
</section>
