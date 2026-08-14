<?php

namespace Tests\Feature;

use App\Models\NewsletterSubscriber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Feature\Auth\AdminTestCase;

/**
 * CP-7 newsletter signup: normalization, server-enforced consent, genuine
 * idempotency for repeat signups, the named error bag, throttling and the
 * privacy posture. Turnstile behaviour lives in TurnstileTest; here the
 * testing-environment bypass applies (no keys configured).
 */
class NewsletterTest extends AdminTestCase
{
    public function test_a_valid_signup_is_stored_with_locale_and_consent(): void
    {
        $response = $this->from($this->publicHost)
            ->post($this->publicHost.'/newsletter', [
                'email' => 'Reader@Example.COM',
                'consent' => '1',
            ]);

        $response->assertRedirect($this->publicHost);
        $response->assertSessionHas('newsletter_status', __('newsletter.success'));

        $subscriber = NewsletterSubscriber::query()->sole();

        // Normalized before validation and persistence: trimmed, lowercased.
        $this->assertSame('reader@example.com', $subscriber->email);
        $this->assertSame('en', $subscriber->locale);
        $this->assertNotNull($subscriber->consented_at);
    }

    public function test_whitespace_and_case_variants_resolve_to_one_subscriber(): void
    {
        $this->post($this->publicHost.'/newsletter', ['email' => "  Reader@Example.com \n", 'consent' => '1']);
        $this->post($this->publicHost.'/newsletter', ['email' => 'READER@EXAMPLE.COM', 'consent' => '1']);

        $this->assertSame(1, NewsletterSubscriber::query()->count());
        $this->assertSame('reader@example.com', NewsletterSubscriber::query()->sole()->email);
    }

    public function test_a_repeat_signup_leaves_the_existing_row_untouched_and_looks_identical(): void
    {
        $existing = NewsletterSubscriber::factory()->create([
            'email' => 'repeat@example.com',
            'locale' => 'ar',
            'consented_at' => now()->subMonth(),
        ]);

        $response = $this->from($this->publicHost)
            ->post($this->publicHost.'/newsletter', [
                'email' => 'repeat@example.com',
                'consent' => '1',
            ]);

        // Same response as a first-time signup: nothing reveals that this
        // address was already on the list.
        $response->assertRedirect($this->publicHost);
        $response->assertSessionHas('newsletter_status', __('newsletter.success'));

        $subscriber = NewsletterSubscriber::query()->sole();

        // firstOrCreate writes nothing to an existing row: the record of the
        // FIRST consent and its locale survive.
        $this->assertSame($existing->getKey(), $subscriber->getKey());
        $this->assertSame('ar', $subscriber->locale);
        $this->assertTrue($existing->consented_at->equalTo($subscriber->consented_at));
    }

    public function test_a_losing_concurrent_insert_recovers_without_poisoning_the_transaction(): void
    {
        $existing = NewsletterSubscriber::factory()->create(['email' => 'race@example.com']);

        // createOrFirst() is the path firstOrCreate() falls through to when
        // its initial SELECT misses — i.e. what a real race reaches. Calling
        // it directly against an existing address forces the INSERT to run
        // and violate the unique index, so the savepoint recovery is what is
        // actually under test here.
        DB::transaction(function () use ($existing): void {
            $result = NewsletterSubscriber::query()->createOrFirst(
                ['email' => 'race@example.com'],
                ['locale' => 'en', 'consented_at' => now()],
            );

            $this->assertSame($existing->getKey(), $result->getKey());

            // Under PostgreSQL a failed statement aborts the whole
            // transaction unless it ran inside a savepoint — so this write
            // succeeding is the proof that recovery worked.
            NewsletterSubscriber::factory()->create(['email' => 'after-race@example.com']);
        });

        $this->assertSame(2, NewsletterSubscriber::query()->count());
    }

    public function test_consent_is_enforced_server_side(): void
    {
        $response = $this->from($this->publicHost)
            ->post($this->publicHost.'/newsletter', ['email' => 'noconsent@example.com']);

        $response->assertSessionHasErrors(['consent'], null, 'newsletter');
        $this->assertSame(0, NewsletterSubscriber::query()->count());
    }

    public function test_errors_land_in_the_named_bag_and_never_in_the_default_one(): void
    {
        $response = $this->from($this->publicHost)
            ->post($this->publicHost.'/newsletter', ['email' => 'not-an-email', 'consent' => '1']);

        $response->assertSessionHasErrors(['email'], null, 'newsletter');

        // The default bag must stay empty, so a newsletter failure can never
        // surface inside the contact form sharing this session. Asserted on
        // the bags directly: assertSessionHasNoErrors() inspects the whole
        // 'errors' key and would fail on the populated newsletter bag.
        $errors = session('errors');

        $this->assertNotNull($errors);
        $this->assertTrue($errors->getBag('newsletter')->has('email'));
        $this->assertTrue($errors->getBag('default')->isEmpty());

        $this->assertSame(0, NewsletterSubscriber::query()->count());
    }

    public function test_hostile_non_string_input_is_rejected_without_a_string_cast(): void
    {
        // An array (and a nested array) reaching normalizeEmail must not
        // raise an array-to-string warning — which could carry submitted
        // data into the log — and must simply fail validation.
        $response = $this->from($this->publicHost)
            ->post($this->publicHost.'/newsletter', [
                'email' => ['nested' => ['deeper' => 'a@b.test']],
                'consent' => '1',
            ]);

        $response->assertSessionHasErrors(['email'], null, 'newsletter');
        $this->assertSame(0, NewsletterSubscriber::query()->count());
        $this->assertSame('', NewsletterSubscriber::normalizeEmail(['a']));
        $this->assertSame('', NewsletterSubscriber::normalizeEmail(null));
        $this->assertSame('', NewsletterSubscriber::normalizeEmail(new \stdClass));
    }

    public function test_the_localized_route_stores_the_visitors_locale_and_answers_in_arabic(): void
    {
        $response = $this->from($this->publicHost.'/ar')
            ->post($this->publicHost.'/ar/newsletter', [
                'email' => 'arabic@example.com',
                'consent' => '1',
            ]);

        $response->assertRedirect($this->publicHost.'/ar');
        $response->assertSessionHas('newsletter_status', __('newsletter.success', [], 'ar'));
        $this->assertSame('ar', NewsletterSubscriber::query()->sole()->locale);
    }

    public function test_signups_are_throttled_per_ip(): void
    {
        RateLimiter::clear('');

        foreach (range(1, 5) as $i) {
            $this->post($this->publicHost.'/newsletter', [
                'email' => "throttle{$i}@example.com",
                'consent' => '1',
            ])->assertRedirect();
        }

        $this->post($this->publicHost.'/newsletter', [
            'email' => 'throttle6@example.com',
            'consent' => '1',
        ])->assertStatus(429);
    }

    public function test_no_requester_metadata_is_stored(): void
    {
        $this->post($this->publicHost.'/newsletter', [
            'email' => 'private@example.com',
            'consent' => '1',
        ]);

        $columns = array_keys(NewsletterSubscriber::query()->sole()->getAttributes());

        $this->assertSame(
            ['id', 'email', 'locale', 'consented_at', 'created_at', 'updated_at'],
            $columns,
        );
    }

    public function test_the_endpoint_does_not_exist_on_the_admin_host(): void
    {
        $this->post($this->adminHost.'/newsletter', [
            'email' => 'admin-host@example.com',
            'consent' => '1',
        ])->assertNotFound();
    }
}
