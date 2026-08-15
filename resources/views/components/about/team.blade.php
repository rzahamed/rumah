{{--
    Paper: About Page → "Section / Team". paddingTop 150, paddingInline 264
    (912px content), gap 32, centred: eyebrow; 40px/120% title; 20px/120%
    muted body max 666. Then the grid — Paper's column gap 32 plus the grid's
    own paddingTop 93, i.e. 125px below the body — cards wrapping at 38px
    gaps, centred: each 437×569 (kept as an aspect ratio so a narrow screen
    scales the card rather than squashing it), radius 20, clipped, the portrait
    as its background, padding 15px 17px, and at its foot a midnight-blue name
    plate (radius 12, 81 tall, 20px inline padding, gap 5) with the 20px/24px
    white name over the 16px/20px #C6D2DB position.

    The cards are wired to the existing TeamMember module — visible members
    in sort order, supplied by PublicController — rather than the four
    placeholder people drawn in Paper; nothing here queries. A member without
    a photo takes the brand surface behind the plate, the module's documented
    fallback, rather than an invented image. The section keeps its heading and
    copy when there are no visible members and simply renders no grid; the
    header's "Team" link targets id="team" here.

    Cards are static in Paper — no link, no detail view — so none is added.
--}}
@props(['members'])

<section id="team" class="pt-4xl desktop:pt-[150px]">
    <div class="container-site">
        <div class="mx-auto flex w-full max-w-[912px] flex-col items-center gap-xl text-center" data-animate="fade-up">
            <x-site.eyebrow :label="__('about.team.eyebrow')" />
            <h2 class="t-section-title">{{ __('about.team.title') }}</h2>
            <p class="t-copy max-w-[666px] leading-[120%] text-text-muted">{{ __('about.team.body') }}</p>
        </div>

        @if ($members->isNotEmpty())
            <ul class="mx-auto mt-2xl flex w-full max-w-[912px] flex-wrap justify-center gap-[38px] desktop:mt-[125px]" data-animate="stagger">
                @foreach ($members as $member)
                    @php($photoUrl = $member->photoUrl())

                    <li @class([
                        'relative flex aspect-[437/569] w-full max-w-[437px] flex-col justify-end overflow-clip rounded-card px-[17px] py-[15px]',
                        'bg-background-brand' => $photoUrl === null,
                    ]) data-animate-item>
                        @if ($photoUrl !== null)
                            <img class="absolute inset-0 size-full object-cover" src="{{ $photoUrl }}" alt="" loading="lazy" decoding="async">
                        @endif

                        <div class="relative flex min-h-[81px] w-full flex-col justify-center gap-[5px] rounded-sm bg-background-brand px-[20px] py-sm">
                            <h3 class="t-copy leading-6 text-text-on-brand">{{ $member->translate('name') }}</h3>
                            <p class="t-ui text-text-on-brand-muted">{{ $member->translate('position') }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</section>
