<?php

namespace Tests\Feature\Auth;

use App\Filament\Pages\Auth\Login;
use App\Models\User;
use App\Support\Turnstile;
use Illuminate\Auth\Events\Attempting;
use Illuminate\Auth\Events\Failed;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * Cloudflare Turnstile on the Filament admin login page.
 *
 * The public boundaries are covered by TurnstileTest; this file covers the one
 * boundary where a successful guess yields a SESSION rather than a database
 * row, so every assertion pairs the expected message with the authentication
 * state — a rejection that still signed the user in would pass a
 * message-only test.
 *
 * NO REAL NETWORK REQUEST CAN OCCUR HERE. preventStrayRequests() in setUp
 * turns any unfaked outbound call into a test failure.
 */
class AdminLoginTurnstileTest extends AdminTestCase
{
    private const TOKEN = 'test-widget-token';

    private const PASSWORD = 'correct-horse-battery-staple';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    /** Configure real keys so verification applies in the testing environment. */
    private function enableTurnstile(): void
    {
        config([
            'platform.turnstile.site_key' => 'test-site-key',
            'platform.turnstile.secret_key' => 'test-secret-key',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function fakeSiteverify(array $payload, int $status = 200): void
    {
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response($payload, $status),
        ]);
    }

    private function panelUser(): User
    {
        $user = $this->admin();

        $user->forceFill(['password' => bcrypt(self::PASSWORD)])->save();

        return $user;
    }

    /**
     * One sign-in attempt. The token is set only when supplied, so an omitted
     * argument reproduces a request that never carried the field at all —
     * which is exactly how a crafted Livewire call would arrive.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function attempt(User $user, ?string $token = null, array $overrides = []): Testable
    {
        $component = Livewire::test(Login::class)
            ->set('data.email', $overrides['email'] ?? $user->email)
            ->set('data.password', $overrides['password'] ?? self::PASSWORD);

        if ($token !== null) {
            $component->set('data.'.Turnstile::FIELD, $token);
        }

        if (array_key_exists('remember', $overrides)) {
            $component->set('data.remember', $overrides['remember']);
        }

        return $component->call('authenticate');
    }

    /** Assert the attempt was refused by Turnstile and left no session behind. */
    private function assertRefused(Testable $component, string $message): void
    {
        $component->assertHasErrors(['data.'.Turnstile::FIELD]);

        $this->assertGuest();

        $errors = $component->errors()->get('data.'.Turnstile::FIELD);

        $this->assertContains($message, $errors);
    }

    // ---- Enabled, valid token -------------------------------------------

    public function test_a_valid_token_and_correct_credentials_sign_the_user_in(): void
    {
        $this->enableTurnstile();
        $this->fakeSiteverify(['success' => true, 'action' => Turnstile::ACTION_ADMIN_LOGIN]);

        $user = $this->panelUser();

        $this->attempt($user, self::TOKEN)->assertHasNoErrors();

        $this->assertAuthenticatedAs($user);
        Http::assertSentCount(1);
    }

    // ---- Missing and blank tokens ---------------------------------------

    public function test_an_absent_token_is_rejected_without_contacting_cloudflare(): void
    {
        $this->enableTurnstile();
        Http::fake();

        // A forged Livewire call that simply omits the property must not slip
        // past: a bare rule object is skipped for an absent attribute, which
        // is why the shared rule set carries an implicit 'required'.
        $this->assertRefused(
            $this->attempt($this->panelUser()),
            __('content.turnstile.required'),
        );

        Http::assertNothingSent();
    }

    public function test_a_blank_token_is_rejected_without_contacting_cloudflare(): void
    {
        $this->enableTurnstile();
        Http::fake();

        $this->assertRefused(
            $this->attempt($this->panelUser(), '   '),
            __('content.turnstile.required'),
        );

        // 'bail' stops before the rule object, so no verification is spent on
        // a value that cannot be valid.
        Http::assertNothingSent();
    }

    // ---- Failure modes ---------------------------------------------------

    public function test_an_unsuccessful_verification_is_rejected(): void
    {
        $this->enableTurnstile();
        $this->fakeSiteverify(['success' => false, 'error-codes' => ['invalid-input-response']]);

        $this->assertRefused(
            $this->attempt($this->panelUser(), self::TOKEN),
            __('content.turnstile.failed'),
        );
    }

    public function test_a_token_minted_for_a_public_form_is_rejected(): void
    {
        $this->enableTurnstile();
        // Cloudflare reports success, but for a PUBLIC action. Both public
        // widgets are reachable anonymously, so without the action check a
        // token harvested from the contact page would open the panel's
        // credential check.
        $this->fakeSiteverify(['success' => true, 'action' => Turnstile::ACTION_PUBLIC_FORM]);

        $this->assertRefused(
            $this->attempt($this->panelUser(), self::TOKEN),
            __('content.turnstile.failed'),
        );
    }

    public function test_an_upstream_outage_fails_closed(): void
    {
        $this->enableTurnstile();
        Http::fake(fn () => throw new ConnectionException('timed out'));

        $this->assertRefused(
            $this->attempt($this->panelUser(), self::TOKEN),
            __('content.turnstile.failed'),
        );
    }

    public function test_a_non_2xx_response_fails_closed(): void
    {
        $this->enableTurnstile();
        $this->fakeSiteverify(['success' => true, 'action' => Turnstile::ACTION_ADMIN_LOGIN], 503);

        $this->assertRefused(
            $this->attempt($this->panelUser(), self::TOKEN),
            __('content.turnstile.failed'),
        );
    }

    public function test_a_reused_token_is_rejected(): void
    {
        $this->enableTurnstile();

        // Cloudflare redeems a token once; the replay comes back as
        // timeout-or-duplicate.
        Http::fake([
            'challenges.cloudflare.com/*' => Http::sequence()
                ->push(['success' => true, 'action' => Turnstile::ACTION_ADMIN_LOGIN])
                ->push(['success' => false, 'error-codes' => ['timeout-or-duplicate']]),
        ]);

        $user = $this->panelUser();

        // First use: wrong password, so the token is spent without a session.
        $this->attempt($user, self::TOKEN, ['password' => 'wrong-password'])
            ->assertHasErrors(['data.email']);

        $this->assertGuest();

        // The verifier caches outcomes for ONE request. Livewire's test
        // harness stays inside a single request lifecycle, so the scoped
        // instance is rebuilt here to reproduce the real per-request
        // boundary — otherwise the cache, not Cloudflare, would answer.
        $this->app->forgetScopedInstances();

        $this->assertRefused(
            $this->attempt($user, self::TOKEN),
            __('content.turnstile.failed'),
        );
    }

    // ---- Ordering: verification precedes the credential check ------------

    public function test_verification_precedes_the_credential_check(): void
    {
        $this->enableTurnstile();
        $this->fakeSiteverify(['success' => false]);

        Event::fake([Attempting::class, Failed::class]);

        $user = $this->panelUser();

        $this->assertRefused(
            $this->attempt($user, self::TOKEN, ['password' => 'wrong-password']),
            __('content.turnstile.failed'),
        );

        // No credential was retrieved, compared, or reported on: the guard
        // was never reached.
        Event::assertNotDispatched(Attempting::class);
        Event::assertNotDispatched(Failed::class);
    }

    public function test_correct_credentials_cannot_authenticate_without_a_verified_token(): void
    {
        $this->enableTurnstile();
        $this->fakeSiteverify(['success' => false]);

        // The credentials are entirely valid. Server-side verification is the
        // authority, so the sign-in must still fail.
        $this->assertRefused(
            $this->attempt($this->panelUser(), self::TOKEN),
            __('content.turnstile.failed'),
        );
    }

    // ---- Spent tokens are replaced --------------------------------------

    public function test_a_failed_attempt_clears_the_token_and_resets_the_widget(): void
    {
        $this->enableTurnstile();
        $this->fakeSiteverify(['success' => true, 'action' => Turnstile::ACTION_ADMIN_LOGIN]);

        $component = $this->attempt(
            $this->panelUser(),
            self::TOKEN,
            ['password' => 'wrong-password'],
        );

        // The token passed verification and is now spent. Leaving it in the
        // form would make the NEXT attempt fail verification rather than the
        // credential check, reporting "we could not verify that you are
        // human" to someone who simply mistyped.
        $component->assertDispatched('turnstile-reset');

        $this->assertNull($component->get('data.'.Turnstile::FIELD));
        $this->assertGuest();
    }

    // ---- Existing login behaviour is preserved ---------------------------

    public function test_credential_failures_stay_generic(): void
    {
        $this->enableTurnstile();
        $this->fakeSiteverify(['success' => true, 'action' => Turnstile::ACTION_ADMIN_LOGIN]);

        $user = $this->panelUser();

        $wrongPassword = $this->attempt($user, self::TOKEN, ['password' => 'wrong-password'])
            ->errors()
            ->get('data.email');

        $this->app->forgetScopedInstances();

        $unknownEmail = $this->attempt($user, self::TOKEN, ['email' => 'nobody@example.com'])
            ->errors()
            ->get('data.email');

        // An attacker must not be able to tell a wrong password from an
        // account that does not exist.
        $this->assertSame($wrongPassword, $unknownEmail);
        $this->assertNotEmpty($wrongPassword);
        $this->assertGuest();
    }

    public function test_remember_me_is_preserved(): void
    {
        $this->enableTurnstile();
        $this->fakeSiteverify(['success' => true, 'action' => Turnstile::ACTION_ADMIN_LOGIN]);

        $user = $this->panelUser();

        $this->attempt($user, self::TOKEN, ['remember' => true])->assertHasNoErrors();

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->getRememberToken());
    }

    public function test_the_rate_limiter_still_applies(): void
    {
        $this->enableTurnstile();
        $this->fakeSiteverify(['success' => true, 'action' => Turnstile::ACTION_ADMIN_LOGIN]);

        $user = $this->panelUser();

        $component = Livewire::test(Login::class)
            ->set('data.email', $user->email)
            ->set('data.password', 'wrong-password')
            ->set('data.'.Turnstile::FIELD, self::TOKEN);

        // Filament allows 5 attempts; the 6th is throttled before the form is
        // even read, so it neither reaches Cloudflare nor the guard.
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $component->call('authenticate');
        }

        // All five attempts reuse one token, and the verifier caches outcomes
        // per request, so exactly one siteverify call has been spent.
        Http::assertSentCount(1);

        $component->call('authenticate')->assertNotified();

        $this->assertGuest();

        // The throttle sits AHEAD of verification: the sixth attempt is
        // refused before the form is read, spending no further call.
        Http::assertSentCount(1);
    }

    // ---- Configuration posture ------------------------------------------

    public function test_a_bypass_environment_signs_in_with_no_widget_and_no_verification(): void
    {
        config(['platform.turnstile.site_key' => null, 'platform.turnstile.secret_key' => null]);
        Http::fake();

        $user = $this->panelUser();

        $this->assertFalse(app(Turnstile::class)->enabled());

        $this->attempt($user)->assertHasNoErrors();

        $this->assertAuthenticatedAs($user);
        Http::assertNothingSent();
    }

    // ---- Widget rendering ------------------------------------------------

    public function test_the_widget_renders_only_when_enabled_and_never_leaks_the_secret(): void
    {
        $url = $this->adminHost.'/login';

        // Disabled (no keys, testing environment): the login page loads no
        // third-party script at all.
        $disabled = $this->get($url)->assertOk()->getContent();

        $this->assertStringNotContainsString('challenges.cloudflare.com', $disabled);
        $this->assertStringNotContainsString('turnstile', $disabled);

        $this->enableTurnstile();

        $html = $this->get($url)->assertOk()->getContent();

        $this->assertStringContainsString('challenges.cloudflare.com/turnstile/v0/api.js', $html);
        $this->assertStringContainsString(Turnstile::ACTION_ADMIN_LOGIN, $html);
        // The PUBLIC key belongs in the markup…
        $this->assertStringContainsString('test-site-key', $html);
        // …the secret never does.
        $this->assertStringNotContainsString('test-secret-key', $html);
    }
}
