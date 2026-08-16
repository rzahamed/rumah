@php
    $locale = app()->getLocale();
    $homeUrl = \App\Support\LocalizedUrl::to($locale, 'home');

    // Same inert-until-routed contract as the header. Contact appears here
    // rather than in the primary bar, and the listing uses the footer's own
    // plural label for the blog.
    $companyLinks = [];

    foreach ([
        'home' => __('site.nav.home'),
        'about' => __('site.nav.about'),
        'services' => __('site.nav.services'),
        'blog.index' => __('site.footer.blogs'),
        'contact' => __('site.nav.contact'),
    ] as $key => $label) {
        $url = \App\Support\LocalizedUrl::to($locale, $key);

        if ($url !== null) {
            $companyLinks[] = ['label' => $label, 'url' => $url];
        }
    }

    $legalLinks = [];

    foreach ([
        'policy.privacy' => __('site.policy.privacy'),
        'policy.terms' => __('site.policy.terms'),
    ] as $key => $label) {
        $url = \App\Support\LocalizedUrl::to($locale, $key);

        if ($url !== null) {
            $legalLinks[] = ['label' => $label, 'url' => $url];
        }
    }
@endphp

{{--
    Paper "Footer": the dark section surface, paddingTop 119 / paddingBottom
    59, paddingInline 180 (1080 content). Top row: 48px/58px wordmark and
    220px-wide columns (28px/34px white heading, 20px/24px #C6D2DB links,
    30px apart). Bottom row, 106px below: 16px copyright and legal links 55px
    apart, both #C6D2DB. Those measurements are the desktop frame's, so they
    apply from the tablet breakpoint; the mobile footer keeps its 80px
    vertical padding.

    Paper's top row is LOGO | Company | Social Media laid out with
    justify-content: space-between, which places the Company column in the
    middle of the row (x 562–782 at 1440) and Social Media at the far edge.
    The Social Media column links to the firm's profiles — the URLs supplied
    by the client are held in $socialLinks below (there is no CMS field for
    them), with the labels from lang/site.php.
--}}
@php
    // The firm's profiles, as supplied; labels come from lang/site.php.
    $socialLinks = [
        'x' => 'https://x.com/@RumahLawFirm',
        'linkedin' => 'https://www.linkedin.com/company/rumah-law-firm-legal-consultancy/',
        'instagram' => 'https://www.instagram.com/rumah.lawfirm',
    ];
@endphp
<footer class="bg-surface-section-dark text-text-on-brand">
    <div class="container-site py-4xl tablet:pt-[119px] tablet:pb-[59px]">
        <div class="mx-auto flex w-full max-w-[1080px] flex-col gap-2xl">
            <div class="flex flex-col gap-2xl tablet:flex-row tablet:items-start tablet:justify-between tablet:gap-4xl">
                <x-site.brand :href="$homeUrl" size="footer" class="text-text-on-brand" />

                @if ($companyLinks !== [])
                    <nav aria-labelledby="footer-company" class="flex flex-col gap-[30px] tablet:w-[220px] tablet:shrink-0">
                        <h2 id="footer-company" class="t-h3 leading-[34px] text-text-on-brand">{{ __('site.footer.company') }}</h2>

                        <ul class="flex flex-col gap-[30px]">
                            @foreach ($companyLinks as $link)
                                <li><a class="t-copy leading-6 text-text-on-brand-muted hover:text-text-on-brand" href="{{ $link['url'] }}">{{ $link['label'] }}</a></li>
                            @endforeach
                        </ul>
                    </nav>
                @endif

                <nav aria-labelledby="footer-social" class="flex flex-col gap-[30px] tablet:w-[220px] tablet:shrink-0">
                    <h2 id="footer-social" class="t-h3 leading-[34px] text-text-on-brand">{{ __('site.footer.social') }}</h2>

                    <ul class="flex flex-col gap-[30px]">
                        @foreach ($socialLinks as $key => $url)
                            <li><a class="t-copy leading-6 text-text-on-brand-muted hover:text-text-on-brand" href="{{ $url }}" rel="noopener">{{ __('site.footer.social_links.'.$key) }}</a></li>
                        @endforeach
                    </ul>
                </nav>
            </div>

            <div class="flex flex-col gap-md pt-2xl tablet:flex-row tablet:items-center tablet:justify-between tablet:gap-10 tablet:pt-[106px]">
                <p class="t-ui text-text-on-brand-muted">&copy; {{ now()->year }} {{ config('platform.brand_name') }}. {{ __('site.footer.rights') }}</p>

                @if ($legalLinks !== [])
                    <nav aria-label="{{ __('site.footer.legal_label') }}">
                        <ul class="flex flex-wrap items-center gap-lg tablet:gap-[55px]">
                            @foreach ($legalLinks as $link)
                                <li><a class="t-ui text-text-on-brand-muted hover:text-text-on-brand" href="{{ $link['url'] }}">{{ $link['label'] }}</a></li>
                            @endforeach
                        </ul>
                    </nav>
                @endif
            </div>
        </div>
    </div>
</footer>
