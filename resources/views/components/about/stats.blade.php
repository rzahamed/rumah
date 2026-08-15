{{--
    Paper: About Page → "Section / Stats". paddingTop 106; paddingLeft 172 and
    paddingRight 191 (1077px content — the 19px asymmetry is Paper's and is
    rendered as a centred 1077px block, a 9.5px difference); three equal
    columns 54px apart, each a 48px/120% figure over a 16px/150% muted
    caption max 323, 32px between them. Same construction as the Home Impact
    statistics.

    The figures are the client's own copy as drawn in Paper — transcribed, not
    verified — and the third caption is Paper's unfinished sentence; see the
    note at the top of lang/en/about.php.

    Marked up as a description list: each figure is the term and its caption
    the description, which is what a screen reader needs to pair them.
--}}
<section class="pt-4xl desktop:pt-[106px]">
    <div class="container-site">
        <dl class="mx-auto grid w-full max-w-[1077px] gap-2xl tablet:grid-cols-3 tablet:gap-[54px]" data-animate="stagger">
            @foreach (__('about.stats') as $stat)
                <div class="flex flex-col gap-xl" data-animate-item>
                    <dt class="t-page-title leading-[120%]">{{ $stat['value'] }}</dt>
                    <dd class="t-ui max-w-[323px] leading-[150%] text-text-muted">{{ $stat['label'] }}</dd>
                </div>
            @endforeach
        </dl>
    </div>
</section>
