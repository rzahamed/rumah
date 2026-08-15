{{--
    Paper: Services Page → "Section / Packages". paddingTop 100, paddingLeft
    183 / paddingRight 190 (1067px content — the 7px asymmetry is Paper's,
    rendered as a centred 1067px block), gap 32, centred: eyebrow; the
    40px/120% "Page Title" — the page's single h1, this being its first and
    primary section; a 20px/120% muted body max 743; then the pricing row —
    Paper's column gap 32 plus the row's own paddingTop 49, i.e. 81px below
    the body — three 345px cards 17px apart, top-aligned and centred, wrapping
    when the width runs out. The row is capped at the cards' own extent,
    345 + 17 + 345 + 17 + 345 = 1069, so all three sit on one line at desktop
    (the 1067px heading block above is 2px narrower, which is why the row
    carries its own maximum). Card anatomy lives in x-services.pricing-card
    (card names are h2).

    The three packages are Paper's copy (lang/services.php); there is no
    packages module in the CMS to read from.
--}}
<section class="pt-4xl desktop:pt-[100px]">
    <div class="container-site">
        <div class="mx-auto flex w-full max-w-[1067px] flex-col items-center gap-xl text-center" data-animate="fade-up">
            <x-site.eyebrow :label="__('services.packages.eyebrow')" />
            <h1 class="t-section-title">{{ __('services.packages.title') }}</h1>
            <p class="t-copy max-w-[743px] leading-[120%] text-text-muted">{{ __('services.packages.body') }}</p>
        </div>

        <ul class="mx-auto mt-2xl flex w-full max-w-[1069px] flex-wrap items-start justify-center gap-[17px] desktop:mt-[81px]" data-animate="stagger">
            @foreach (__('services.packages.items') as $package)
                <x-services.pricing-card :package="$package" />
            @endforeach
        </ul>
    </div>
</section>
