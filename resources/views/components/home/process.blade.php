{{--
    Paper: Home Page → "Section / Process". 1056px content, gap 32, centred:
    eyebrow; 40px/120% title; 20px/120% muted body max 618. Then, 56px on, the
    timeline: rows 88 apart, each a 473×315 photograph (radius 20) | a 36px
    rail column (4px #D6DDE2 line, a 36px midnight-blue disc with the 20px/24px
    white step number, 4px line — the last step has no lower line) | a 473px
    copy column 24 apart: 28px/120% title over 16px/150% muted body max 397.
    The three are space-between at 37px minimum. Step 2 mirrors: copy (right-
    aligned) | rail | photograph. 64px below the timeline, the primary CTA.

    Photographs are Paper's (resources/images/home/process-{1,2,3}.png). Paper
    draws the rail per row, so it is interrupted by the 88px row gap exactly as
    in the frame. Below tablet the three columns stack — photograph, then the
    numbered copy — and the rail column is not shown, since a vertical rail
    beside a stacked block has nothing to connect. The step number is
    decorative: the <ol> already gives assistive technology each step's
    position.
--}}
@props(['calBookingUrl' => null])

@php($processImages = [
    'resources/images/home/process-1.png',
    'resources/images/home/process-2.png',
    'resources/images/home/process-3.png',
])

<section class="section">
    <div class="container-site">
        <div class="mx-auto flex w-full max-w-[1056px] flex-col items-center gap-xl text-center" data-animate="fade-up">
            <x-site.eyebrow :label="__('home.process.eyebrow')" />
            <h2 class="t-section-title">{{ __('home.process.title') }}</h2>
            <p class="t-copy max-w-[618px] leading-[120%] text-text-muted">{{ __('home.process.body') }}</p>
        </div>

        {{-- Paper's column gap 32 plus the timeline's own paddingTop 56 = 88
             below the section body; likewise gap 32 + the CTA frame's
             paddingTop 64 = 96 below the timeline. --}}
        <ol class="mx-auto mt-xl flex w-full max-w-[1056px] flex-col gap-2xl tablet:mt-[88px] tablet:gap-[88px]">
            @foreach (__('home.process.steps') as $step)
                <li class="flex flex-col gap-lg tablet:grid tablet:grid-cols-[1fr_36px_1fr] tablet:items-center tablet:gap-[37px]">
                    <img
                        @class(['aspect-[473/315] w-full max-w-[473px] rounded-card object-cover', 'tablet:order-3 tablet:justify-self-end' => $loop->even])
                        data-animate="{{ $loop->even ? 'reveal-end' : 'reveal-start' }}"
                        src="{{ Vite::asset($processImages[$loop->index]) }}"
                        alt=""
                        loading="lazy"
                        decoding="async"
                    >

                    <div class="hidden self-stretch flex-col items-center tablet:order-2 tablet:flex" aria-hidden="true">
                        <span class="w-1 grow bg-rail"></span>
                        <span class="t-copy flex size-9 shrink-0 items-center justify-center rounded-full bg-background-brand leading-6 text-text-on-brand">{{ $loop->iteration }}</span>
                        <span @class(['w-1 grow', 'bg-rail' => ! $loop->last])></span>
                    </div>

                    <div @class(['flex w-full max-w-[473px] flex-col gap-lg', 'tablet:order-1 tablet:items-end tablet:text-end' => $loop->even, 'tablet:order-3' => ! $loop->even]) data-animate="{{ $loop->even ? 'reveal-start' : 'reveal-end' }}">
                        <div class="flex items-center gap-sm">
                            <span class="t-copy flex size-9 shrink-0 items-center justify-center rounded-full bg-background-brand leading-6 text-text-on-brand tablet:hidden" aria-hidden="true">{{ $loop->iteration }}</span>
                            <h3 class="t-h3">{{ $step['title'] }}</h3>
                        </div>
                        <p class="t-ui max-w-[397px] leading-[150%] text-text-muted">{{ $step['body'] }}</p>
                    </div>
                </li>
            @endforeach
        </ol>

        @if ($calBookingUrl !== null)
            <div class="flex justify-center pt-2xl tablet:pt-[96px]" data-animate="fade-up">
                <x-site.cta-button :href="$calBookingUrl" :label="__('site.cta.book_call')" />
            </div>
        @endif
    </div>
</section>
