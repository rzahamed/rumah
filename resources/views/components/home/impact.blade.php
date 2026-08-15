{{--
    Paper: Home Page → "Section / Impact". 1080px content. Eyebrow; 48px
    below it the head row (40px/110% title, 400 wide, beside 20px/140% muted
    body max 583, 80px apart); then the statistics — Paper's column gap of 48
    plus the stat row's own paddingTop of 56, i.e. 104px below the head row —
    three equal columns 54px apart, each a 48px/120% figure over a 16px/150%
    muted sentence max 323. The distances are written as margins on each
    block rather than one uniform gap so each Paper measurement is legible.

    The three figures are the client's own copy as drawn in Paper. They are
    transcribed, not verified — see the note at the top of lang/en/home.php.

    Marked up as a description list: each figure is the term and its sentence
    the description, which is what a screen reader needs to pair them.
--}}
<section class="section">
    <div class="container-site">
        <div class="mx-auto flex w-full max-w-[1080px] flex-col">
            <x-site.eyebrow :label="__('home.impact.eyebrow')" data-animate="fade-up" />

            <div class="mt-2xl flex flex-col gap-xl tablet:flex-row tablet:items-start tablet:justify-between tablet:gap-4xl" data-animate="fade-up">
                <h2 class="t-section-title leading-[110%] tablet:w-[400px] tablet:shrink-0">{{ __('home.impact.title') }}</h2>
                <p class="t-copy leading-[140%] text-text-muted tablet:max-w-[583px]">{{ __('home.impact.body') }}</p>
            </div>

            <dl class="mt-2xl grid gap-2xl tablet:mt-[104px] tablet:grid-cols-3 tablet:gap-[54px]" data-animate="stagger">
                @foreach (__('home.impact.stats') as $stat)
                    <div class="flex flex-col gap-xl" data-animate-item>
                        <dt class="t-page-title">{{ $stat['value'] }}</dt>
                        <dd class="t-ui max-w-[323px] leading-[150%] text-text-muted">{{ $stat['label'] }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </div>
</section>
