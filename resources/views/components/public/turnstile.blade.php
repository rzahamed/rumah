{{--
    Cloudflare Turnstile widget. Renders NOTHING when verification is
    disabled (an unconfigured local/testing environment), so no third-party
    script is requested during development or automated tests.

    The action attribute must match the action the server-side rule expects
    for this form — that pairing is what stops a token minted on one form
    from being replayed against another. The widget requires JavaScript;
    that is the accepted trade-off recorded for this checkpoint, and the
    server remains the authority either way.
--}}
@props(['action', 'bag' => 'default'])
@php($turnstile = app(\App\Support\Turnstile::class))
@if ($turnstile->enabled())
    <div class="turnstile">
        <div class="cf-turnstile"
            data-sitekey="{{ config('platform.turnstile.site_key') }}"
            data-action="{{ $action }}"
            data-language="{{ app()->getLocale() }}"></div>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
        @error(\App\Support\Turnstile::FIELD, $bag)
            <p class="form-field__error turnstile__error" role="alert">{{ $message }}</p>
        @enderror
    </div>
@endif
