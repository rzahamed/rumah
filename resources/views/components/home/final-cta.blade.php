{{--
    Paper: Home Page → "Section / Final CTA". paddingBlock 120 (this is the
    last section, so it carries the closing 120 too — .section-end);
    paddingInline 253, i.e. a 934px card: desert-gold surface, radius 20, the
    0 2px 12 #3232321F elevation, padding 93px 168px, gap 24, centred: a
    40px/125% white title max 598, a 16px/140% white subtitle max 496, and
    "Button / On Accent" — white pill, 16px dark label, 22px dark arrow. The
    168px inline padding is the desktop card's; below tablet the card keeps
    the ordinary 24px so the copy has room.

    CONTRAST WARNING — needs your decision. Paper sets this card's text to
    --color-text-on-brand (#FFFFFF) on --color-background-accent (#AC9379).
    That is roughly 2.3:1. WCAG AA wants 4.5:1 for the 16px subtitle and 3:1
    for the 40px title, so both fail as drawn. The design is reproduced
    faithfully here rather than silently recoloured; switching the card to
    --color-text-on-light (#000000) reaches about 8.6:1 and fixes it with a
    token that already exists.
--}}
@props(['calBookingUrl' => null])

<section class="section section-end">
    <div class="container-site">
        <div class="mx-auto flex w-full max-w-[934px] flex-col items-center gap-lg rounded-card bg-background-accent px-lg py-[93px] text-center shadow-panel tablet:px-[168px]" data-animate="fade-up">
            <h2 class="t-section-title max-w-[598px] leading-[125%] text-text-on-brand">{{ __('home.final_cta.title') }}</h2>
            <p class="t-ui max-w-[496px] leading-[140%] text-text-on-brand">{{ __('home.final_cta.subtitle') }}</p>

            @if ($calBookingUrl !== null)
                <x-site.cta-button :href="$calBookingUrl" :label="__('site.cta.book_call')" variant="on-accent" />
            @endif
        </div>
    </div>
</section>
