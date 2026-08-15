{{--
    Paper: Contact Page → "Section / Contact Hero". paddingTop 116,
    paddingInline 190 (1060px content), a row (space-between, gap 80): a
    574px copy column (gap 24: eyebrow, 48px/120% title, 20px/145% muted
    body) beside the 407×335 photograph, radius 20. The photograph is
    Paper's (resources/images/contact/hero.png), decorative, alt="".

    The two-column row is a desktop measurement; below the desktop breakpoint
    the photograph stacks under the copy.
--}}
<section class="pt-2xl desktop:pt-[116px]">
    <div class="container-site">
        <div class="mx-auto flex w-full max-w-[1060px] flex-col gap-xl desktop:flex-row desktop:items-center desktop:justify-between desktop:gap-4xl">
            <div class="flex w-full flex-col gap-lg desktop:w-[574px] desktop:shrink-0" data-animate="reveal-start">
                <x-site.eyebrow :label="__('contact.hero.eyebrow')" />
                <h1 class="t-page-title leading-[120%]">{{ __('contact.hero.title') }}</h1>
                <p class="t-copy leading-[145%] text-text-muted">{{ __('contact.hero.body') }}</p>
            </div>

            <img
                class="aspect-[407/335] w-full max-w-[407px] shrink-0 rounded-card object-cover"
                data-animate="reveal-end"
                src="{{ Vite::asset('resources/images/contact/hero.png') }}"
                alt=""
                width="407"
                height="335"
                fetchpriority="high"
                decoding="async"
            >
        </div>
    </div>
</section>
