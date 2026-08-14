<?php

namespace App\Http\Controllers;

use App\Models\NewsletterSubscriber;
use App\Rules\ValidTurnstileToken;
use App\Support\Turnstile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Public newsletter signup. Serves both routing variants (default locale at
 * the root path and the localized one), throttled per IP by the named
 * 'newsletter' limiter.
 *
 * Privacy and safety posture, matching the form pipeline:
 *
 * - The address is normalized BEFORE validation, so the validator, old()
 *   repopulation and persistence all see one identical value.
 * - Failures go to the NAMED 'newsletter' error bag, never the default one,
 *   so newsletter errors can never surface inside the contact form (or any
 *   other public form) that shares a page or a session.
 * - Consent is enforced server-side ('accepted'), never by client script.
 * - A repeat signup is a genuine no-op: firstOrCreate() leaves the existing
 *   row untouched (original locale and consent moment preserved) and the
 *   response is identical to a first-time signup, so nothing reveals who is
 *   already subscribed.
 * - No requester metadata is stored, and neither the address nor the
 *   Turnstile token is ever logged.
 */
class NewsletterController extends Controller
{
    /** Root path → default-locale signup. */
    public function store(Request $request): RedirectResponse
    {
        app()->setLocale(config('platform.default_locale'));

        return $this->subscribe($request);
    }

    /** Localized signup (/{locale}/newsletter). SetLocale has already run. */
    public function storeLocalized(Request $request): RedirectResponse
    {
        return $this->subscribe($request);
    }

    private function subscribe(Request $request): RedirectResponse
    {
        // Normalize first. normalizeEmail() turns hostile non-string input
        // (arrays, nested arrays, objects) into an empty string rather than
        // casting it, so no array-to-string warning can carry submitted data
        // into the log; validation then rejects it like any other bad value.
        $request->merge([
            'email' => NewsletterSubscriber::normalizeEmail($request->input('email')),
        ]);

        $validated = $request->validateWithBag(
            'newsletter',
            [
                'email' => ['required', 'string', 'email', 'max:254'],
                'consent' => ['accepted'],
                Turnstile::FIELD => ValidTurnstileToken::rules(Turnstile::ACTION_NEWSLETTER),
            ],
            [
                'email.required' => __('newsletter.errors.email'),
                'email.email' => __('newsletter.errors.email'),
                'email.max' => __('newsletter.errors.email'),
                'consent.accepted' => __('newsletter.errors.consent'),
                ...ValidTurnstileToken::messages(),
            ],
            [],
        );

        // firstOrCreate: the second array is written ONLY on creation, so a
        // returning subscriber keeps the locale and consent moment of their
        // first signup. The framework wraps the insert in a savepoint and
        // re-queries on a unique violation, which is what makes a concurrent
        // duplicate safe under PostgreSQL — a failed statement would
        // otherwise poison the surrounding transaction.
        NewsletterSubscriber::query()->firstOrCreate(
            ['email' => $validated['email']],
            [
                'locale' => app()->getLocale(),
                'consented_at' => now(),
            ],
        );

        return redirect()
            ->back()
            ->with('newsletter_status', __('newsletter.success'));
    }
}
