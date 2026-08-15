{{--
    Paper: Contact Page → "Section / FAQ". paddingTop 88, paddingLeft 335 /
    paddingRight 334 (771px content, centred), gap 28: eyebrow; 40px/120%
    title; 20px/24px muted body; then the accordion — Paper's column gap 28
    plus the accordion's own paddingTop 77, i.e. 105px below the body — items
    22px apart.

    "Accordion Item / Collapsed": #EDEDED, radius 20, the 0 2px 12 #00000033
    elevation, 98px tall, 58px inline padding, the 24px/30px question beside
    a 24px chevron. "Accordion Item / Expanded": the same surface with 11px
    padding and gap 22 — the trigger row inset 47px (58 − 11) with 22px above
    it, and the answer in a white panel (radius 20, same elevation, padding
    36, 16px/150% muted copy max 657). Paper draws the chevron identically in
    both states, so it is not rotated.

    Each item is a native <details>/<summary>: opens and closes by mouse,
    Enter and Space with no script, and the element itself announces its
    state. Questions and answers are the CMS FAQ module's visible rows,
    supplied by PublicController — the four Paper draws are not transcribed.
    Answers are authored as plain text in the panel (a textarea), so they
    render escaped with line breaks preserved. With no visible FAQ the head
    renders and the list simply does not.
--}}
@props(['faqs'])

<section class="pt-4xl desktop:pt-[88px]">
    <div class="container-site">
        <div class="mx-auto flex w-full max-w-[771px] flex-col items-center text-center">
            <x-site.eyebrow :label="__('contact.faq.eyebrow')" data-animate="fade-up" />
            <h2 class="t-section-title mt-[28px]" data-animate="fade-up">{{ __('contact.faq.title') }}</h2>
            <p class="t-copy mt-[28px] leading-6 text-text-muted" data-animate="fade-up">{{ __('contact.faq.body') }}</p>

            @if ($faqs->isNotEmpty())
                <div class="mt-2xl flex w-full flex-col gap-[22px] text-start desktop:mt-[105px]">
                    @foreach ($faqs as $faq)
                        <details class="group w-full rounded-card bg-surface-tint shadow-accordion open:p-[11px]">
                            <summary class="flex min-h-[98px] cursor-pointer list-none items-center justify-between gap-lg rounded-card px-lg [&::-webkit-details-marker]:hidden group-open:min-h-0 group-open:px-[47px] group-open:pt-[22px] group-open:pb-[22px] tablet:px-[58px]">
                                <h3 class="t-card-title leading-[30px] text-text-primary">{{ $faq->translate('question') }}</h3>
                                <x-site.icon.chevron class="size-6 text-text-primary" />
                            </summary>
                            <div class="rounded-card bg-white p-lg shadow-accordion tablet:p-[36px]">
                                <p class="t-ui max-w-[657px] whitespace-pre-line leading-[150%] text-text-muted">{{ $faq->translate('answer') }}</p>
                            </div>
                        </details>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</section>
