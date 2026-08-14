<?php

namespace App\Rules;

use App\Support\Turnstile;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Turnstile check for one submission boundary. The rule is constructed with
 * the action the form's widget declares (Turnstile::ACTION_PUBLIC_FORM or
 * ACTION_NEWSLETTER), so a token minted on one form cannot be replayed
 * against another.
 *
 * The rule holds no verification logic of its own: it delegates to the
 * container-scoped Turnstile service, which is the only place a siteverify
 * request is ever built. Two messages are possible and neither carries any
 * upstream detail — 'required' states that the visitor has not completed
 * the widget, and 'failed' is the single neutral outcome for every
 * server-side rejection (bad token, wrong action, timeout, outage,
 * malformed response).
 */
class ValidTurnstileToken implements ValidationRule
{
    public function __construct(private readonly string $expectedAction) {}

    /**
     * The complete rule set for a boundary's token field.
     *
     * 'required' is NOT decoration. Laravel skips a non-implicit rule
     * object when its attribute is absent from the request
     * (Validator::presentOrRuleIsImplicit), so a rule object alone would
     * let an attacker bypass verification by simply omitting the field.
     * The implicit 'required' rule closes that hole.
     *
     * 'bail' stops at the first failure, so a present-but-blank value
     * produces the single localized "complete the check" message instead of
     * both that and the rule's own, and no verification request is made for
     * a value that cannot be valid.
     *
     * When verification is disabled (an unconfigured local or testing
     * environment) the field carries no rules at all.
     *
     * @return list<mixed>
     */
    public static function rules(string $expectedAction): array
    {
        if (! app(Turnstile::class)->enabled()) {
            return [];
        }

        return ['bail', 'required', new self($expectedAction)];
    }

    /**
     * Message overrides pairing with rules(): the implicit 'required'
     * failure must read as the same neutral prompt the rule itself uses.
     *
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            Turnstile::FIELD.'.required' => __('content.turnstile.required'),
        ];
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $turnstile = app(Turnstile::class);

        if (! $turnstile->enabled()) {
            return;
        }

        // A non-string (array, object, null) is a crafted request, not a
        // completed widget: treat it exactly like an absent token and never
        // pass it on for a string cast.
        if (! is_string($value) || trim($value) === '') {
            $fail(__('content.turnstile.required'));

            return;
        }

        if (! $turnstile->verify($value, $this->expectedAction)) {
            $fail(__('content.turnstile.failed'));
        }
    }
}
