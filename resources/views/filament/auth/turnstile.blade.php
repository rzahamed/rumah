{{--
    Cloudflare Turnstile widget for the Filament panel's login form.

    Separate from <x-public.turnstile> on purpose: that component is styled for
    the public site and reports into the public error bags, while this one
    renders inside Filament's field wrapper (which supplies the label and the
    validation message) and drives a Livewire property rather than a posted
    form field.

    The component is only ever placed in the schema when verification applies,
    so there is nothing to guard here — an unconfigured local or testing
    environment requests no third-party script at all.

    THE SERVER IS THE AUTHORITY. Everything below is convenience: the token is
    verified in App\Rules\ValidTurnstileToken during form validation, before a
    single credential is read. If this script never loads, is blocked by an
    extension, or is bypassed entirely, the sign-in still fails — which is why
    the submit button is only disabled once the widget has actually rendered.
    Disabling it unconditionally would turn a blocked script into an admin
    login nobody can use, with no message explaining why.
--}}
@php
    $statePath = $getStatePath();
@endphp

<div
    wire:ignore
    x-data="{
        widgetId: null,

        init() {
            this.$wire.on('turnstile-reset', () => this.reset());

            this.load();
        },

        /*
         * One script for the page, loaded explicitly so the widget is
         * rendered by this component rather than by a DOM scan — the login
         * form is a Livewire component, and an auto-rendered widget would be
         * lost the moment anything re-renders around it.
         */
        load() {
            if (window.turnstile) {
                this.render();

                return;
            }

            if (! window.turnstileScriptLoaded) {
                window.turnstileScriptLoaded = new Promise((resolve, reject) => {
                    window.onloadTurnstileCallback = resolve;

                    const script = document.createElement('script');
                    script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?onload=onloadTurnstileCallback&render=explicit';
                    script.async = true;
                    script.defer = true;
                    script.onerror = reject;

                    document.head.appendChild(script);
                });
            }

            // A rejected promise leaves the button enabled and the state
            // empty: the server then answers with the localized 'complete the
            // security check' message instead of a silently dead form.
            window.turnstileScriptLoaded
                .then(() => this.render())
                .catch(() => {});
        },

        render() {
            if (! window.turnstile) {
                return;
            }

            this.widgetId = window.turnstile.render(this.$refs.widget, {
                sitekey: @js(config('platform.turnstile.site_key')),
                action: @js($turnstileAction),
                language: @js(app()->getLocale()),
                theme: 'light', // The panel is light-mode only.
                callback: (token) => {
                    this.$wire.set(@js($statePath), token, false);
                    this.disableSubmit(false);
                },
                // A token that expires or errors is worthless; drop it rather
                // than let it be submitted.
                'expired-callback': () => this.clear(),
                'error-callback': () => this.clear(),
            });

            // Only take responsibility for the button once a widget really
            // exists to satisfy it.
            if (this.widgetId !== undefined && this.widgetId !== null) {
                this.disableSubmit(true);
            }
        },

        /*
         * Turnstile tokens are single-use, so a spent one must be replaced
         * before the next attempt — otherwise a mistyped password turns every
         * later attempt into a verification failure.
         */
        reset() {
            this.clear();

            if (this.widgetId === null || ! window.turnstile) {
                return;
            }

            this.$nextTick(() => window.turnstile.reset(this.widgetId));
        },

        clear() {
            this.$wire.set(@js($statePath), null, false);

            if (this.widgetId !== null) {
                this.disableSubmit(true);
            }
        },

        /*
         * Filament renders the submit button, so it is reached through the
         * form rather than owned here. Re-applied after every reset, because
         * a Livewire re-render replaces the button element.
         */
        disableSubmit(disabled) {
            this.$nextTick(() => {
                const button = this.$el.closest('form')?.querySelector('button[type=submit]');

                if (! button) {
                    return;
                }

                button.disabled = disabled;
                button.setAttribute('aria-disabled', disabled ? 'true' : 'false');
            });
        },
    }"
>
    <div x-ref="widget"></div>
</div>
