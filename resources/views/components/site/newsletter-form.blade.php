{{--
    Paper: Home Page → "Section / Insights" → "Card / Newsletter": the brand
    surface, radius 20, padding 34px 36px, gap 24; a 32px/120% white heading;
    a 16px #C6D2DB field label over a white 54px field (radius 12, 18px inline
    padding, #8A8A8A placeholder); a 12px consent line beside a 12px white
    checkbox; and a white 135×43 pill reading "Subscribe" at 18px/22px.

    Posts to the EXISTING newsletter endpoint — no new route, controller or
    validation. The controller normalizes the address, enforces consent with
    'accepted' server-side, verifies Turnstile against ACTION_NEWSLETTER, and
    throttles per IP via the named 'newsletter' limiter. Nothing here weakens
    any of that: the HTML `required` attributes are a convenience only.

    Errors are read from the NAMED 'newsletter' bag, which is what keeps them
    out of the contact form when both share a page. Success arrives as the
    'newsletter_status' flash, identical for a first-time and a repeat signup.

    The markup is a standard POST with a CSRF token, so the card stays fully
    readable and operable with scripting off. Submission is a different matter:
    where Turnstile verification is enabled, no token is minted without its
    script and the server will correctly reject the request. This form is not
    usable without JavaScript in that configuration.

    Strings come from lang/{en,ar}/newsletter.php. The Home card overrides the
    heading and adds a placeholder from lang/{en,ar}/home.php, because Paper
    gives this card its own copy; a caller that passes nothing gets the shared
    newsletter strings.

    VARIANTS — one implementation, two presentations. The default is the Home
    card above, unchanged. variant="blog" is Paper's Blog Index "Card /
    Newsletter" FORM ONLY: no card surface or heading of its own (the caller,
    x-blog.newsletter-card, supplies those and any copy through the slot), a
    13px/16px field label, a row of the white 39px field (radius 8, 14px
    inline padding, 14px/18px placeholder) and the white 85×39 "Subscribe"
    pill at 13px/16px, then the consent line 16px from its checkbox. Endpoint,
    CSRF, error bag, consent, Turnstile and strings are shared by both.
--}}
@props(['heading' => null, 'placeholder' => null, 'variant' => 'default'])

@php
    $locale = app()->getLocale();

    // The two routing variants are separate routes, exactly as elsewhere.
    $action = $locale === (string) config('platform.default_locale', 'en')
        ? route('public.newsletter.subscribe')
        : route('public.newsletter.subscribe.localized', ['locale' => $locale]);

    // Inert until the privacy policy is routed: the middle fragment then
    // becomes a link, and stays plain text until it is.
    $privacyUrl = \App\Support\LocalizedUrl::to($locale, 'policy.privacy');

    $bag = $errors->getBag('newsletter');

    // Normalized to exactly the two presentations this component defines.
    $blog = $variant === 'blog';
@endphp

@unless ($blog)
<section
    {{ $attributes->merge(['class' => 'newsletter-card flex flex-col gap-lg rounded-card bg-background-brand px-[36px] py-[34px] text-text-on-brand']) }}
    aria-labelledby="newsletter-heading"
>
    <h3 id="newsletter-heading" class="t-h2">{{ $heading ?? __('newsletter.heading') }}</h3>
@endunless

    @if (session('newsletter_status'))
        <p class="t-ui" role="status">{{ session('newsletter_status') }}</p>
    @endif

    @if ($bag->isNotEmpty())
        <p class="form-field__error t-caption" role="alert">{{ __('newsletter.error_summary') }}</p>
    @endif

    <form method="POST" action="{{ $action }}" @class(['flex flex-col', 'gap-lg' => ! $blog, 'w-full gap-[15px]' => $blog])>
        @csrf

        @if ($blog)
            {{ $slot }}
        @endif

        <div @class(['flex flex-col', 'gap-[14px]' => ! $blog, 'gap-[15px]' => $blog])>
            <div @class(['flex flex-col', 'gap-xs' => ! $blog, 'gap-[7px]' => $blog])>
                <label @class(['text-text-on-brand-muted', 't-ui' => ! $blog, 't-tag' => $blog]) for="newsletter-email">{{ __('newsletter.email_label') }}</label>

                @if ($blog)
                <div class="flex w-full items-center gap-xs">
                @endif
                <input
                    @class([
                        'w-full bg-white text-text-primary placeholder:text-text-placeholder',
                        't-ui h-[54px] rounded-sm px-[18px]' => ! $blog,
                        't-caption h-[39px] min-w-0 grow rounded-chip px-[14px] leading-[18px]' => $blog,
                    ])
                    id="newsletter-email"
                    name="email"
                    type="email"
                    autocomplete="email"
                    required
                    value="{{ old('email') }}"
                    @if ($placeholder !== null) placeholder="{{ $placeholder }}" @endif
                    @if ($bag->has('email')) aria-invalid="true" aria-describedby="newsletter-email-error" @endif
                >
                @if ($blog)
                    <button class="t-tag flex h-[39px] w-[85px] shrink-0 items-center justify-center rounded-pill bg-white text-text-primary transition-colors hover:bg-text-on-brand-muted" type="submit">{{ __('newsletter.submit') }}</button>
                </div>
                @endif

                @if ($bag->has('email'))
                    <p class="form-field__error t-caption" id="newsletter-email-error">{{ $bag->first('email') }}</p>
                @endif
            </div>

            <div class="flex flex-col gap-xs">
                <div @class(['flex items-start', 'gap-sm' => ! $blog, 'gap-md' => $blog])>
                    {{-- Paper's box is 12px, too small a target on its own, so
                         the <label> wrapping it is the 24×24 hit area (pulled
                         back by its own margin so the visible box still sits
                         where Paper places it). The visible sentence beside it
                         is the control's DESCRIPTION, not its label: a link
                         inside a <label> would toggle the checkbox instead of
                         following the link. --}}
                    <label class="-m-[6px] grid size-6 shrink-0 place-items-center">
                        <input
                            class="checkbox-inverse"
                            id="newsletter-consent"
                            name="consent"
                            type="checkbox"
                            value="1"
                            required
                            @checked(old('consent'))
                            @if ($bag->has('consent')) aria-invalid="true" @endif
                            aria-describedby="newsletter-consent-text @if ($bag->has('consent')) newsletter-consent-error @endif"
                        >
                        <span class="sr-only">{{ __('newsletter.consent_label') }}</span>
                    </label>

                    <p class="text-fine leading-[135%] text-text-on-brand-muted" id="newsletter-consent-text">{{ __('newsletter.consent_before') }}@if ($privacyUrl !== null)<a class="underline hover:text-text-on-brand" href="{{ $privacyUrl }}">{{ __('newsletter.consent_link') }}</a>@else{{ __('newsletter.consent_link') }}@endif{{ __('newsletter.consent_after') }}</p>
                </div>

                @if ($bag->has('consent'))
                    <p class="form-field__error t-caption" id="newsletter-consent-error">{{ $bag->first('consent') }}</p>
                @endif
            </div>
        </div>

        <x-public.turnstile :action="\App\Support\Turnstile::ACTION_NEWSLETTER" bag="newsletter" />

        @unless ($blog)
        <button class="t-badge inline-flex h-[43px] w-fit min-w-[135px] items-center justify-center rounded-pill bg-white px-lg leading-[22px] text-text-primary transition-colors hover:bg-text-on-brand-muted" type="submit">{{ __('newsletter.submit') }}</button>
        @endunless
    </form>

@unless ($blog)
</section>
@endunless
