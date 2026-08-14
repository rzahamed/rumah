<?php

namespace App\Filament\Pages\Auth;

use App\Rules\ValidTurnstileToken;
use App\Support\Turnstile;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Throwable;

/**
 * Cloudflare Turnstile on the panel's credential check. The public site's two
 * submission boundaries were already covered; an unauthenticated login form on
 * a known admin hostname is the remaining one, and it is the only boundary
 * where a successful guess yields a session rather than a database row.
 *
 * The check reuses the single verifier (App\Support\Turnstile) and the single
 * rule (App\Rules\ValidTurnstileToken) — no second siteverify request is built
 * here, so every guarantee documented on those classes carries over unchanged,
 * including the hardcoded local/testing bypass that no deployment variable can
 * extend.
 *
 * ORDERING. The token is a form field rather than an explicit call ahead of
 * parent::authenticate(), and that is deliberate. The parent runs
 * rateLimit(5), then $this->form->getState(), which validates. Verification
 * therefore lands AFTER Filament's throttle and BEFORE any credential is
 * retrieved, compared or turned into a session. Verifying ahead of the
 * throttle would leave every unthrottled request spending one outbound
 * siteverify call while protecting nothing extra: no password is touched
 * either way.
 */
class Login extends BaseLogin
{
    /**
     * The base components plus the widget. Appended last so it sits directly
     * above the submit button, and only when verification applies — a
     * bypassed environment renders no widget and requests no third-party
     * script, exactly as the public boundaries behave.
     */
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getRememberFormComponent(),
                ...(app(Turnstile::class)->enabled() ? [$this->getTurnstileFormComponent()] : []),
            ]);
    }

    /**
     * Field state holds the widget's token; the rule set is the shared one.
     *
     * The name is Turnstile::FIELD so the panel and the public forms speak of
     * the token by one key. Nothing else reads it: the parent's
     * getCredentialsFromFormData() names 'email' and 'password' explicitly, so
     * the token cannot reach the auth guard however this field is populated.
     *
     * validationMessages() rather than ValidTurnstileToken::messages(): that
     * helper keys on the bare request attribute, which is right for the
     * controllers but never matches Filament's 'data.'-prefixed error keys.
     * The rule object supplies the 'failed' message itself.
     */
    protected function getTurnstileFormComponent(): Component
    {
        return ViewField::make(Turnstile::FIELD)
            ->view('filament.auth.turnstile')
            ->viewData(['turnstileAction' => Turnstile::ACTION_ADMIN_LOGIN])
            ->label(__('content.turnstile.label'))
            ->hiddenLabel()
            ->rules(ValidTurnstileToken::rules(Turnstile::ACTION_ADMIN_LOGIN))
            ->validationMessages([
                'required' => __('content.turnstile.required'),
            ]);
    }

    /**
     * Every outcome that is not a completed sign-in must return a fresh
     * widget.
     *
     * Turnstile tokens are single-use. Without this, a wrong password would
     * leave the spent token in the form, and the NEXT attempt would fail
     * verification instead of the credential check — reporting "we could not
     * verify that you are human" to someone who simply mistyped, and doing so
     * on every subsequent attempt.
     *
     * The reset covers all three non-success paths: a thrown
     * ValidationException (bad credentials, or the token itself), a null
     * return from the rate limiter, and a null return from a multi-factor
     * challenge.
     */
    public function authenticate(): ?LoginResponse
    {
        try {
            $response = parent::authenticate();
        } catch (Throwable $exception) {
            $this->resetTurnstile();

            throw $exception;
        }

        if ($response === null) {
            $this->resetTurnstile();
        }

        return $response;
    }

    /**
     * Clear the spent token and ask the browser widget for a new one. Both
     * halves matter: dropping only the state would leave the widget showing a
     * solved check it can no longer honour.
     */
    protected function resetTurnstile(): void
    {
        if (! app(Turnstile::class)->enabled()) {
            return;
        }

        $this->data[Turnstile::FIELD] = null;

        $this->dispatch('turnstile-reset');
    }
}
