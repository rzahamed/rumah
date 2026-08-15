{{--
    Paper: Services Page → "Section / Package Add-ons". paddingTop 61,
    paddingLeft 189 / paddingRight 190 (1061px content, centred), two panels
    61px apart. Each panel is a #EEEEEE frame (radius 12, padding 7, gap 8)
    of white blocks (radius 12).

    "Panel / Litigation Add-ons": a 258px aside (padding 34px 20px, gap 35,
    vertically centred) — a row (gap 18) of the 26px midnight-blue badge
    (radius 4, 16px glyph) and the 20px/24px title, a 16px/125% muted body;
    then (gap 20) "Benefits for Package Holders:" at 16px/20px, the discount
    pill (brand pill, padding 4px 12px 4px 4px, gap 8, pill elevation: 16px
    price glyph then a white 13px/16px label pill padded 4px 12px), and a
    16px/125% muted line — beside a growing body (padding 64px 41px, gap 24,
    centred) of four rows: 22px check, 20px gap, 16px/125% midnight-blue text.

    "Panel / Key Advantages": the growing body first (padding 54, gap 24, five
    rows) beside a 258px aside (padding 62px 26px, gap 28, centred) of the
    badge and the 32px/120% centred title.

    Copy is Paper's (lang/services.php). Below the desktop breakpoint each
    panel stacks — aside above body for the first, body above aside for the
    second, keeping Paper's DOM order — and the aside spans the width. The
    row order and inline paddings are logical, so both panels mirror in Arabic.
--}}
<section class="pt-4xl desktop:pt-[61px]">
    <div class="container-site">
        <div class="mx-auto flex w-full max-w-[1061px] flex-col gap-2xl desktop:gap-[61px]">
            <div class="flex flex-col gap-xs rounded-sm bg-surface-frame p-[7px] desktop:flex-row" data-animate="fade-up">
                <div class="flex w-full flex-col justify-center gap-[35px] rounded-sm bg-surface-card px-[20px] py-[34px] desktop:w-[258px] desktop:shrink-0">
                    <div class="flex flex-col gap-lg">
                        <div class="flex items-center gap-[18px]">
                            <span class="flex size-[26px] shrink-0 items-center justify-center rounded-[4px] bg-background-brand p-[5px] text-text-on-brand" aria-hidden="true"><x-site.icon.package class="size-4" /></span>
                            <h2 class="t-copy leading-6 text-text-primary">{{ __('services.addons.title') }}</h2>
                        </div>
                        <p class="t-ui leading-[125%] text-text-muted">{{ __('services.addons.body') }}</p>
                    </div>

                    <div class="flex flex-col gap-[20px]">
                        <p class="t-ui text-text-primary">{{ __('services.addons.benefit_label') }}</p>
                        {{-- Same nested-pill spacing as x-site.cta-button, mirrored: the
                             CTA is 4px inset / white pill / 8px gap / glyph / 12px end
                             padding, so here it is 12px start padding / glyph / 8px gap /
                             white pill / 4px end inset — the glyph sits in a 36px exposed
                             blue area on the START side and both rounded ends read
                             cleanly; logical start/end so EN/AR mirror. The footprint is
                             Paper's: label + 40, 32 tall. --}}
                        <p class="inline-flex w-fit shrink-0 items-center gap-xs whitespace-nowrap rounded-pill bg-background-brand py-2xs ps-sm pe-2xs text-text-on-brand shadow-pill">
                            <x-site.icon.price-tag class="size-4" />
                            <span class="t-tag rounded-pill bg-white px-sm py-2xs text-text-primary">{{ __('services.addons.discount') }}</span>
                        </p>
                        <p class="t-ui leading-[125%] text-text-muted">{{ __('services.addons.benefit_body') }}</p>
                    </div>
                </div>

                <ul class="flex grow flex-col justify-center gap-lg rounded-sm bg-surface-card px-lg py-xl desktop:px-[41px] desktop:py-[64px]">
                    @foreach (__('services.addons.items') as $item)
                        <li class="flex items-start gap-[20px]">
                            <x-site.icon.check class="size-[22px] text-midnight-blue" />
                            <span class="t-ui min-w-0 leading-[125%] text-midnight-blue">{{ $item }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="flex flex-col gap-xs rounded-sm bg-surface-frame p-[7px] desktop:flex-row" data-animate="fade-up">
                <ul class="flex grow flex-col justify-center gap-lg rounded-sm bg-surface-card p-lg desktop:p-[54px]">
                    @foreach (__('services.advantages.items') as $item)
                        <li class="flex items-start gap-[20px]">
                            <x-site.icon.check class="size-[22px] text-midnight-blue" />
                            <span class="t-ui min-w-0 leading-[125%] text-midnight-blue">{{ $item }}</span>
                        </li>
                    @endforeach
                </ul>

                <div class="flex w-full flex-col items-center justify-center gap-[28px] rounded-sm bg-surface-card px-[26px] py-[62px] text-center desktop:w-[258px] desktop:shrink-0">
                    <span class="flex size-[26px] shrink-0 items-center justify-center rounded-[4px] bg-background-brand p-[5px] text-text-on-brand" aria-hidden="true"><x-site.icon.package class="size-4" /></span>
                    <h2 class="t-h2">{{ __('services.advantages.title') }}</h2>
                </div>
            </div>
        </div>
    </div>
</section>
