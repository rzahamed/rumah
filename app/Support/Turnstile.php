<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use LogicException;
use Throwable;

/**
 * Cloudflare Turnstile verification — the SINGLE boundary through which
 * every check passes. Nothing else in the application builds a siteverify
 * request, so the guarantees below hold for both public submission paths
 * (admin-defined forms and the newsletter) and for the admin login page.
 *
 * Resolved as a CONTAINER-SCOPED instance (see AppServiceProvider): the
 * verification cache below lives on the instance, and scoped bindings are
 * rebuilt for every request — including under long-lived workers such as
 * Octane, where a static cache would leak outcomes between requests and
 * grow without bound. Nothing relies on a manual reset.
 *
 * Guarantees:
 *
 * - The secret, the token and Cloudflare's response body never leave this
 *   class: verify() returns a bare bool and the only diagnostic emitted is
 *   a fixed string plus an exception CLASS NAME. No submitted data, no
 *   error codes and no response payload can reach the log or a rendered
 *   message.
 * - The request is bounded and attempted exactly once. Retries are
 *   deliberately absent: a retry silently multiplies the timeout budget on
 *   a public request path.
 * - Every failure mode collapses to false — success:false, action
 *   mismatch, non-2xx, malformed JSON, connection error, timeout — so no
 *   caller can render one failure differently from another. The whole
 *   operation, decoding included, sits inside one try: a malformed body
 *   must produce a neutral rejection, never a 500.
 * - Protection cannot be switched off by a deployment variable. There is no
 *   enforce flag; only the hardcoded bypass_environments list exempts an
 *   environment, and assertConfigured() fails closed everywhere else.
 */
final class Turnstile
{
    private const ENDPOINT = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /**
     * The request key Cloudflare's widget submits. Single-sourced here
     * because it is referenced as a validation key, as a never-persisted
     * key in the form pipeline, and as a never-flashed key in
     * bootstrap/app.php.
     */
    public const string FIELD = 'cf-turnstile-response';

    /** Widget action for the shared admin-defined form endpoint. */
    public const string ACTION_PUBLIC_FORM = 'public-form';

    /** Widget action for newsletter signups. */
    public const string ACTION_NEWSLETTER = 'newsletter';

    /**
     * Widget action for the Filament admin login page. Distinct from the
     * public actions so a token minted on the contact form or the newsletter
     * — both reachable anonymously — can never be replayed against the
     * panel's credential check.
     */
    public const string ACTION_ADMIN_LOGIN = 'admin-login';

    /**
     * Verification outcomes for THIS request only.
     *
     * Keyed by a SHA-256 digest of the token AND the expected action, so an
     * outcome can never satisfy a different token or a different form's
     * action. Turnstile tokens are single-use, so one token must be
     * redeemed at most once per request.
     *
     * @var array<string, bool>
     */
    private array $results = [];

    /** Both keys present and non-empty. */
    public function configured(): bool
    {
        return filled(config('platform.turnstile.site_key'))
            && filled(config('platform.turnstile.secret_key'));
    }

    /** The current environment is on the hardcoded bypass list. */
    public function bypassed(): bool
    {
        return app()->environment((array) config('platform.turnstile.bypass_environments', []));
    }

    /**
     * Whether verification applies to this request.
     *
     * Configured                       → enabled anywhere, including local:
     *                                    set real keys locally and the real
     *                                    check runs, no flag needed.
     * Unconfigured, bypass environment → disabled (no widget, no check).
     * Unconfigured, anywhere else      → still enabled, so a misconfigured
     *                                    deployment rejects submissions
     *                                    rather than silently accepting
     *                                    them. assertConfigured() normally
     *                                    catches this at boot, long before
     *                                    any request arrives.
     */
    public function enabled(): bool
    {
        return $this->configured() || ! $this->bypassed();
    }

    /**
     * Fail closed at boot when a non-bypass environment has incomplete
     * keys — the same posture PUBLIC_APP_URL and ADMIN_DOMAIN take. The
     * message names the variables, never their values.
     */
    public function assertConfigured(): void
    {
        if ($this->configured() || $this->bypassed()) {
            return;
        }

        throw new LogicException(
            'TURNSTILE_SITE_KEY and TURNSTILE_SECRET_KEY must both be configured in the "'
            .app()->environment().'" environment. Public form and newsletter submissions and the '
            .'admin login are protected by Turnstile, and this application fails closed rather '
            .'than accepting unverified requests.'
        );
    }

    /**
     * Verify one token for one specific form action.
     *
     * A token is accepted only when Cloudflare reports success AND the
     * action it was issued for matches the one this form declares, so a
     * token minted by the newsletter widget cannot be replayed against the
     * contact form.
     */
    public function verify(?string $token, string $expectedAction): bool
    {
        if (! is_string($token) || trim($token) === '') {
            return false;
        }

        $key = hash('sha256', $token.'|'.$expectedAction);

        if (array_key_exists($key, $this->results)) {
            return $this->results[$key];
        }

        return $this->results[$key] = $this->request($token, $expectedAction);
    }

    private function request(string $token, string $expectedAction): bool
    {
        $timeout = (int) config('platform.turnstile.timeout', 5);

        try {
            $response = Http::asForm()
                ->connectTimeout(min(3, $timeout))
                ->timeout($timeout)
                ->post(self::ENDPOINT, [
                    'secret' => (string) config('platform.turnstile.secret_key'),
                    'response' => $token,
                    // 'remoteip' is deliberately NOT sent: this application
                    // collects no requester metadata, and transmitting the
                    // visitor's IP to a third party would break that.
                ]);

            if (! $response->successful()) {
                return false;
            }

            // Decoding stays inside the try: a malformed body throws, and
            // that must be a neutral rejection like every other failure.
            $data = $response->json();

            if (! is_array($data)) {
                return false;
            }

            return ($data['success'] ?? false) === true
                && ($data['action'] ?? null) === $expectedAction;
        } catch (Throwable $e) {
            // Class name only. No message, no context array, no payload — a
            // Guzzle exception message can carry request detail into the log.
            Log::warning('Turnstile verification unavailable', ['exception' => $e::class]);

            return false;
        }
    }
}
