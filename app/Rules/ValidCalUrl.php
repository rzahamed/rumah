<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Cal.com booking URL allowlist: HTTPS only; no credentials, port, query,
 * or fragment; a required safe path; a 255-character maximum; and a host
 * that is exactly — or a subdomain of — an approved Cal host
 * (CAL_ALLOWED_HOSTS, default cal.com). White-label Cal domains are
 * supported by extending that env list; arbitrary hosts, schemes, and
 * embed HTML are never accepted. The static predicate is shared with the
 * SiteSettings persistence guard so the form and the model can never
 * disagree.
 */
class ValidCalUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::passes($value)) {
            $fail(__('settings.cal_url_invalid'));
        }
    }

    public static function passes(string $value): bool
    {
        if ($value === '' || strlen($value) > 255) {
            return false;
        }

        $parts = parse_url($value);

        if (! is_array($parts) || ($parts['scheme'] ?? null) !== 'https') {
            return false;
        }

        // Credentials, explicit ports, query strings and fragments have no
        // place in a booking-page URL — reject rather than sanitize.
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])
            || isset($parts['query']) || isset($parts['fragment'])) {
            return false;
        }

        $host = strtolower($parts['host'] ?? '');

        if ($host === '' || preg_match('#^/[A-Za-z0-9._/-]{1,255}$#', $parts['path'] ?? '') !== 1) {
            return false;
        }

        foreach (config('platform.cal_allowed_hosts', ['cal.com']) as $allowed) {
            $allowed = strtolower(trim((string) $allowed));

            if ($allowed !== '' && ($host === $allowed || str_ends_with($host, '.'.$allowed))) {
                return true;
            }
        }

        return false;
    }
}
