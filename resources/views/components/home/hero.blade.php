{{--
    Paper: Home Page → "Section / Hero". paddingTop 84, 1080px content, gap
    32: copy (48px/120% title and 16px/140% subtitle, both max 640), the three
    trust pills (16px dot + 16px label, 40px apart), the CTA group (primary +
    secondary, 17px apart), then the photograph — 1080×569, radius 20 — 52px
    below the group.

    One element of that frame is deliberately not rendered: the "Trusted by
    industry leaders" logo marquee is five grey #D9D9D9 rectangles in the
    design — placeholders, not client logos.

    The secondary "View All Services" button resolves through LocalizedUrl and
    so is absent until the Services page is routed; it appears by itself when
    that route lands rather than shipping as a dead button.

    The photograph is Paper's hero image (resources/images/home/hero.png). It
    is illustrative and carries no information the copy does not, so its alt
    is empty; it is the largest above-the-fold asset, hence fetchpriority.
--}}
@props(['calBookingUrl' => null])

@php($servicesUrl = \App\Support\LocalizedUrl::to(app()->getLocale(), 'services'))

<section class="pt-2xl desktop:pt-[84px]">
    <div class="container-site">
        <div class="mx-auto flex w-full max-w-[1080px] flex-col items-center gap-xl text-center">
            <div class="flex max-w-[640px] flex-col items-center gap-lg" data-animate="fade-up">
                <h1 class="t-page-title">{{ __('home.hero.title') }}</h1>
                <p class="t-ui leading-[140%] text-text-primary">{{ __('home.hero.subtitle') }}</p>
            </div>

            <ul class="flex flex-wrap items-center justify-center gap-x-[40px] gap-y-md" data-animate="fade-up">
                @foreach (__('home.hero.pills') as $pill)
                    <li class="flex items-center gap-xs">
                        <span class="size-4 shrink-0 rounded-full bg-midnight-blue" aria-hidden="true"></span>
                        <span class="t-ui">{{ $pill }}</span>
                    </li>
                @endforeach
            </ul>

            @if ($calBookingUrl !== null || $servicesUrl !== null)
                <div class="flex flex-wrap items-center justify-center gap-[17px]" data-animate="fade-up">
                    @if ($calBookingUrl !== null)
                        <x-site.cta-button :href="$calBookingUrl" :label="__('site.cta.book_call')" />
                    @endif

                    @if ($servicesUrl !== null)
                        <a class="btn btn-secondary" href="{{ $servicesUrl }}">{{ __('site.cta.view_services') }}</a>
                    @endif
                </div>
            @endif

            <div class="w-full pt-md desktop:pt-[52px]">
                <img
                    class="aspect-[1080/569] w-full rounded-card object-cover"
                    data-animate="scale"
                    src="{{ Vite::asset('resources/images/home/hero.png') }}"
                    alt=""
                    width="1081"
                    height="593"
                    fetchpriority="high"
                    decoding="async"
                >
            </div>
        </div>
    </div>
</section>
