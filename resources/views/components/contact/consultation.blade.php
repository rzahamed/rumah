{{--
    Paper: Contact Page → "Section / Consultation Process". paddingBlock 125,
    paddingLeft 183 / paddingRight 192 (1065px content, centred), gap 81: a
    head (gap 22: 32px/120% title, 20px/145% muted body max 730), then a row
    (space-between, gap 57): the 290px "Timeline / Consultation Steps" and
    the 715px "Card / Consultation Form".

    TIMELINE — four steps, each a row (gap 18, paddingBottom 56 except the
    last): a 36px rail column (36px midnight-blue disc with the 20px/24px
    white number, then a 4px #D6DDE2 line filling the step's height — the
    last step has no line) beside the copy (gap 22: 20px/24px title,
    16px/140% muted body max 218). The step number is decorative: the <ol>
    already gives assistive technology each step's position.

    FORM CARD — midnight-blue, radius 20, padding 52px 12px 12px; a header
    (padding-inline-start 32, paddingBottom 45, gap 22: 32px/120% white
    title — an h3 nested under this section's h2, keeping Paper's 32px
    style — and a 20px/24px #C6D2DB intro) over the white form panel, which
    is x-contact.form rendering the CMS's canonical Contact form. When that
    form is missing or inactive nothing is invented in its place: the card is
    not rendered and the timeline stands alone.

    The two-column row is a desktop measurement; below it the timeline stacks
    above the card and both span the container.
--}}
@props(['form' => null])

<section class="py-4xl desktop:py-[125px]">
    <div class="container-site">
        <div class="mx-auto flex w-full max-w-[1065px] flex-col gap-2xl desktop:gap-[81px]">
            <div class="flex flex-col gap-[22px]" data-animate="fade-up">
                <h2 class="t-h2">{{ __('contact.process.title') }}</h2>
                <p class="t-copy max-w-[730px] leading-[145%] text-text-muted">{{ __('contact.process.body') }}</p>
            </div>

            <div class="flex flex-col gap-2xl desktop:flex-row desktop:items-start desktop:justify-between desktop:gap-[57px]">
                <ol class="flex w-full flex-col desktop:w-[290px] desktop:shrink-0" data-animate="stagger">
                    @foreach (__('contact.process.steps') as $step)
                        <li @class(['flex w-full items-start gap-[18px]', 'pb-[56px]' => ! $loop->last]) data-animate-item>
                            <div class="flex w-9 shrink-0 flex-col items-center self-stretch" aria-hidden="true">
                                <span class="t-copy flex size-9 shrink-0 items-center justify-center rounded-full bg-background-brand leading-6 text-text-on-brand">{{ $loop->iteration }}</span>
                                @unless ($loop->last)
                                    <span class="w-1 grow bg-rail"></span>
                                @endunless
                            </div>
                            <div class="flex grow flex-col gap-[22px]">
                                <h3 class="t-copy leading-6 text-text-primary">{{ $step['title'] }}</h3>
                                <p class="t-ui max-w-[218px] leading-[140%] text-text-muted">{{ $step['body'] }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>

                @if ($form !== null)
                    <div class="flex w-full flex-col rounded-card bg-background-brand px-sm pt-[52px] pb-sm desktop:w-[715px] desktop:shrink-0">
                        <div class="flex flex-col gap-[22px] ps-lg pb-[45px] tablet:ps-xl">
                            <h3 class="t-h2 text-text-on-brand">{{ __('contact.form.title') }}</h3>
                            <p class="t-copy leading-6 text-text-on-brand-muted">{{ __('contact.form.intro') }}</p>
                        </div>

                        <x-contact.form :form="$form" />
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>
