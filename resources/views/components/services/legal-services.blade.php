{{--
    Paper: Services Page → "Section / Legal Services". paddingTop 174,
    paddingLeft 177 / paddingRight 190 (1073px content, centred), gap 32,
    centred: eyebrow; the 40px/120% title; a 20px/120% muted body max 666;
    then the grid — Paper's column gap 32 plus the grid's own paddingTop 113,
    i.e. 145px below the body — cards wrapping at 19px gaps, top-aligned and
    centred, two per row at desktop (527 + 19 + 527 = 1073).

    "Card / Service": white, radius 24, the 0 2px 40 #7E7E7E1F elevation,
    padding 10; a 507×357 thumbnail (radius 20) — the client's photograph
    for that service, resources/images/services/*, one per card in the
    grid's order, shown at Paper's frame by object-fit; then the body
    (padding 42px 26px 30px, gap 24): the 28px/120% title, a 16px/135% muted
    intro, the item list (padding 24px 6px, rows 24 apart, each a 22px check
    20px before 16px/20px midnight-blue text), and a 16px/135% muted closing
    line.

    All ten services are the client's copy from the approved prototype
    (lang/services.php); there is no services module in the CMS. Cards are
    static — no link — so none is added; the thumbnail is decorative, alt="".
--}}
@php($serviceImages = [
    'legalCons.png',
    'contra.jpeg',
    'tmReg.jpeg',
    'courtRep.jpeg',
    'disRes.jpeg',
    'forma.jpeg',
    'gov.jpeg',
    'compli.jpeg',
    'comLaw.jpeg',
    'perStat.jpeg',
])

<section class="pt-4xl desktop:pt-[174px]">
    <div class="container-site">
        <div class="mx-auto flex w-full max-w-[1073px] flex-col items-center gap-xl text-center" data-animate="fade-up">
            <x-site.eyebrow :label="__('services.services.eyebrow')" />
            <h2 class="t-section-title">{{ __('services.services.title') }}</h2>
            <p class="t-copy max-w-[666px] leading-[120%] text-text-muted">{{ __('services.services.body') }}</p>
        </div>

        <ul class="mx-auto mt-2xl flex w-full max-w-[1073px] flex-wrap items-start justify-center gap-[19px] desktop:mt-[145px]" data-animate="stagger">
            @foreach (__('services.services.items') as $service)
                <li class="flex w-full max-w-[527px] flex-col rounded-[24px] bg-surface-card p-[10px] shadow-float" data-animate-item>
                    <img
                        class="aspect-[507/357] w-full rounded-card object-cover"
                        src="{{ Vite::asset('resources/images/services/'.$serviceImages[$loop->index]) }}"
                        alt=""
                        width="507"
                        height="357"
                        loading="lazy"
                        decoding="async"
                    >

                    <div class="flex w-full flex-col gap-lg px-[26px] pt-[42px] pb-[30px]">
                        <h3 class="t-h3">{{ $service['title'] }}</h3>
                        <p class="t-ui leading-[135%] text-text-muted">{{ $service['intro'] }}</p>

                        <ul class="flex w-full flex-col gap-lg rounded-card px-[6px] py-lg">
                            @foreach ($service['items'] as $item)
                                <li class="flex items-center gap-[20px]">
                                    <x-site.icon.check class="size-[22px] text-midnight-blue" />
                                    <span class="t-ui min-w-0 text-midnight-blue">{{ $item }}</span>
                                </li>
                            @endforeach
                        </ul>

                        <p class="t-ui leading-[135%] text-text-muted">{{ $service['outro'] }}</p>
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
</section>
