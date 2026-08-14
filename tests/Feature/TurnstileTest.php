<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\NewsletterSubscriber;
use App\Support\Turnstile;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Auth\AdminTestCase;
use Tests\Support\InteractsWithContactForm;

/**
 * CP-7 Cloudflare Turnstile across BOTH public submission boundaries: the
 * shared admin-defined form endpoint and the newsletter. Every failure mode
 * runs against both, because they are separate controllers that must not
 * drift apart.
 *
 * Each boundary is a separate data-provider CASE rather than a loop inside
 * one test: a fresh application per case means HTTP fakes and session state
 * cannot bleed between them (a second Http::fake() does not displace the
 * first matching stub, which would silently invert what is being asserted).
 *
 * NO REAL NETWORK REQUEST CAN OCCUR HERE. preventStrayRequests() in setUp
 * turns any unfaked outbound call into a test failure, so "we never call
 * Cloudflare in tests" is enforced rather than intended.
 */
class TurnstileTest extends AdminTestCase
{
    use InteractsWithContactForm;

    private const TOKEN = 'test-widget-token';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function boundaries(): array
    {
        return [
            'admin-defined form' => ['form'],
            'newsletter' => ['newsletter'],
        ];
    }

    /** Configure real keys so verification applies in the testing environment. */
    private function enableTurnstile(): void
    {
        config([
            'platform.turnstile.site_key' => 'test-site-key',
            'platform.turnstile.secret_key' => 'test-secret-key',
        ]);
    }

    private function fakeSiteverify(array $payload, int $status = 200): void
    {
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response($payload, $status),
        ]);
    }

    /** The action a boundary's widget declares. */
    private function actionFor(string $boundary): string
    {
        return $boundary === 'form' ? Turnstile::ACTION_PUBLIC_FORM : Turnstile::ACTION_NEWSLETTER;
    }

    /**
     * The error bag a boundary reports into. Always a string: 'default' is
     * the bag name Laravel's assertions expect, and passing null would not
     * select it.
     */
    private function bagFor(string $boundary): string
    {
        return $boundary === 'form' ? 'default' : 'newsletter';
    }

    /** Rows persisted by a boundary. */
    private function persistedCount(string $boundary): int
    {
        return $boundary === 'form'
            ? FormSubmission::query()->count()
            : NewsletterSubscriber::query()->count();
    }

    /** Submit a valid payload to one boundary, with an optional token. */
    private function submit(string $boundary, ?string $token = null, array $overrides = []): TestResponse
    {
        $token = $token === null ? [] : [Turnstile::FIELD => $token];

        if ($boundary === 'form') {
            $this->genericContactForm();

            return $this->from($this->publicHost)
                ->post($this->publicHost.'/forms/contact', array_merge([
                    'name' => 'Visitor',
                    'email' => 'visitor@example.com',
                    'message' => 'Hello there.',
                ], $token, $overrides));
        }

        return $this->from($this->publicHost)
            ->post($this->publicHost.'/newsletter', array_merge([
                'email' => 'subscriber@example.com',
                'consent' => '1',
            ], $token, $overrides));
    }

    /** Assert the boundary rejected the submission with the neutral message. */
    private function assertRejected(TestResponse $response, string $boundary, string $message): void
    {
        $response->assertSessionHasErrors(
            [Turnstile::FIELD => $message],
            null,
            $this->bagFor($boundary),
        );

        $this->assertSame(0, $this->persistedCount($boundary), $boundary);
    }

    // ---- Enabled, valid token -------------------------------------------

    #[DataProvider('boundaries')]
    public function test_a_valid_token_lets_the_boundary_through(string $boundary): void
    {
        $this->enableTurnstile();
        $this->fakeSiteverify(['success' => true, 'action' => $this->actionFor($boundary)]);

        // No argument: this asserts no error bag exists at all, which is
        // exactly what a successful submission must leave behind.
        $this->submit($boundary, self::TOKEN)->assertSessionHasNoErrors();

        $this->assertSame(1, $this->persistedCount($boundary), $boundary);
        Http::assertSentCount(1);
    }

    // ---- Missing token ---------------------------------------------------

    #[DataProvider('boundaries')]
    public function test_an_absent_token_is_rejected_without_contacting_cloudflare(string $boundary): void
    {
        $this->enableTurnstile();
        Http::fake();

        // Omitting the field entirely must not slip past: a bare rule object
        // is skipped for an absent attribute, which is why the rule set
        // carries an implicit 'required'.
        $this->assertRejected(
            $this->submit($boundary),
            $boundary,
            __('content.turnstile.required'),
        );

        Http::assertNothingSent();
    }

    #[DataProvider('boundaries')]
    public function test_a_blank_token_is_rejected_without_contacting_cloudflare(string $boundary): void
    {
        $this->enableTurnstile();
        Http::fake();

        $this->assertRejected(
            $this->submit($boundary, '   '),
            $boundary,
            __('content.turnstile.required'),
        );

        // 'bail' stops before the rule object, so no verification is spent
        // on a value that cannot be valid.
        Http::assertNothingSent();
    }

    // ---- Failure modes, proven on both boundaries ------------------------

    #[DataProvider('boundaries')]
    public function test_an_unsuccessful_verification_is_rejected(string $boundary): void
    {
        $this->enableTurnstile();
        $this->fakeSiteverify(['success' => false, 'error-codes' => ['invalid-input-response']]);

        $this->assertRejected(
            $this->submit($boundary, self::TOKEN),
            $boundary,
            __('content.turnstile.failed'),
        );
    }

    #[DataProvider('boundaries')]
    public function test_a_token_minted_for_another_form_is_rejected(string $boundary): void
    {
        $this->enableTurnstile();
        // Cloudflare reports success, but for the OTHER boundary's action —
        // a replay of a token across forms.
        $other = $boundary === 'form' ? Turnstile::ACTION_NEWSLETTER : Turnstile::ACTION_PUBLIC_FORM;
        $this->fakeSiteverify(['success' => true, 'action' => $other]);

        $this->assertRejected(
            $this->submit($boundary, self::TOKEN),
            $boundary,
            __('content.turnstile.failed'),
        );
    }

    #[DataProvider('boundaries')]
    public function test_an_upstream_outage_fails_closed(string $boundary): void
    {
        $this->enableTurnstile();
        Http::fake(fn () => throw new ConnectionException('timed out'));

        $this->assertRejected(
            $this->submit($boundary, self::TOKEN),
            $boundary,
            __('content.turnstile.failed'),
        );
    }

    #[DataProvider('boundaries')]
    public function test_a_non_2xx_response_fails_closed(string $boundary): void
    {
        $this->enableTurnstile();
        $this->fakeSiteverify(['success' => true, 'action' => $this->actionFor($boundary)], 503);

        $this->assertRejected(
            $this->submit($boundary, self::TOKEN),
            $boundary,
            __('content.turnstile.failed'),
        );
    }

    #[DataProvider('boundaries')]
    public function test_a_malformed_response_body_fails_closed(string $boundary): void
    {
        $this->enableTurnstile();
        // Decoding happens inside the verifier's try: a malformed body must
        // be a neutral rejection, never a 500.
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response('<<not json>>', 200),
        ]);

        $this->assertRejected(
            $this->submit($boundary, self::TOKEN),
            $boundary,
            __('content.turnstile.failed'),
        );
    }

    // ---- The token never reaches the session ----------------------------

    public function test_a_rejected_token_is_never_flashed_into_old_input(): void
    {
        $this->enableTurnstile();
        $this->fakeSiteverify(['success' => false]);

        $this->submit('newsletter', self::TOKEN, ['email' => 'Broken@Example.com'])
            ->assertSessionHasErrors([Turnstile::FIELD], null, 'newsletter');

        $old = session('_old_input', []);

        // The single-use credential must not sit in session storage…
        $this->assertArrayNotHasKey(Turnstile::FIELD, $old);
        $this->assertNull(session('_old_input.'.Turnstile::FIELD));
        // …while ordinary input is still repopulated, in its normalized form.
        $this->assertSame('broken@example.com', $old['email'] ?? null);
    }

    // ---- Configuration posture ------------------------------------------

    public function test_an_incomplete_non_bypass_environment_fails_closed(): void
    {
        config(['platform.turnstile.site_key' => null, 'platform.turnstile.secret_key' => null]);
        app()->detectEnvironment(fn (): string => 'production');

        $turnstile = app(Turnstile::class);

        $this->assertTrue($turnstile->enabled(), 'production must never silently disable verification');
        $this->expectException(\LogicException::class);
        $turnstile->assertConfigured();
    }

    public function test_bypass_applies_only_to_the_configured_environments(): void
    {
        config(['platform.turnstile.site_key' => null, 'platform.turnstile.secret_key' => null]);

        // testing: bypassed, so an unverified submission succeeds and no
        // widget or verification call exists.
        $turnstile = app(Turnstile::class);
        $this->assertTrue($turnstile->bypassed());
        $this->assertFalse($turnstile->enabled());
        $turnstile->assertConfigured();

        $this->submit('newsletter')->assertSessionHas('newsletter_status');

        $this->assertSame(1, NewsletterSubscriber::query()->count());
        Http::assertNothingSent();

        // production with the same empty keys: not bypassed.
        app()->detectEnvironment(fn (): string => 'production');
        $this->assertFalse(app(Turnstile::class)->bypassed());
    }

    // ---- Widget rendering ------------------------------------------------

    /**
     * The component is rendered directly rather than through a page, so the
     * widget's contract holds for whichever public pages a frontend places
     * it on.
     */
    private function renderWidget(string $action): string
    {
        // The component reports validation errors via @error, which reads the
        // bag ShareErrorsFromSession shares on every real request. Rendering
        // outside the middleware stack has to share it explicitly.
        $this->withViewErrors([]);

        return Blade::render(
            '<x-public.turnstile :action="$action" />',
            ['action' => $action],
        );
    }

    public function test_the_widget_renders_only_when_enabled_and_never_leaks_the_secret(): void
    {
        $actions = [Turnstile::ACTION_PUBLIC_FORM, Turnstile::ACTION_NEWSLETTER];

        // Disabled (no keys, testing environment): nothing is emitted, so no
        // third-party script is ever requested.
        foreach ($actions as $action) {
            $disabled = $this->renderWidget($action);

            $this->assertStringNotContainsString('cf-turnstile', $disabled, $action);
            $this->assertStringNotContainsString('challenges.cloudflare.com', $disabled, $action);
        }

        $this->enableTurnstile();

        foreach ($actions as $action) {
            $html = $this->renderWidget($action);

            // The action pairs the widget with the rule that verifies it; a
            // mismatch is what stops a token being replayed across forms.
            $this->assertStringContainsString('data-action="'.$action.'"', $html, $action);
            // The PUBLIC key belongs in the markup…
            $this->assertStringContainsString('data-sitekey="test-site-key"', $html, $action);
            // …the secret never does.
            $this->assertStringNotContainsString('test-secret-key', $html, $action);
            // Exactly one widget script — a second copy would re-initialise
            // the widget.
            $this->assertSame(
                1,
                substr_count($html, 'challenges.cloudflare.com/turnstile/v0/api.js'),
                $action,
            );
        }
    }

    // ---- The token is never stored --------------------------------------

    public function test_a_form_declaring_the_token_field_still_never_stores_it(): void
    {
        $this->enableTurnstile();
        $this->fakeSiteverify(['success' => true, 'action' => Turnstile::ACTION_PUBLIC_FORM]);

        // A conflicting definition — as a text field AND as a checkbox,
        // since the checkbox normalizer runs after the declared-field filter
        // and would otherwise re-add the key.
        foreach (['text', 'checkbox'] as $index => $type) {
            FormSubmission::query()->delete();
            Form::query()->delete();

            Form::factory()->create([
                'slug' => 'conflicting',
                'fields' => [
                    ['name' => 'name', 'type' => 'text', 'required' => true, 'label' => ['en' => 'Name']],
                    [
                        'name' => Turnstile::FIELD,
                        'type' => $type,
                        'required' => false,
                        'label' => ['en' => 'Token'],
                    ],
                ],
            ]);

            $this->from($this->publicHost)
                ->post($this->publicHost.'/forms/conflicting', [
                    'name' => 'Visitor',
                    Turnstile::FIELD => self::TOKEN.'-conflict-'.$index,
                ])
                ->assertSessionHas('status', __('content.forms.submitted'));

            $payload = FormSubmission::query()->sole()->payload;

            $this->assertArrayNotHasKey(Turnstile::FIELD, $payload, "type {$type}");
            $this->assertSame(['name' => 'Visitor'], $payload, "type {$type}");
        }
    }
}
