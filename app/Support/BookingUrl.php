<?php

namespace App\Support;

use App\Models\SiteSettings;
use App\Rules\ValidCalUrl;

/**
 * The site's booking URL, validated on the READ path.
 *
 * SiteSettings guards this column when it is saved, but a direct database
 * write bypasses model events and the value ends up as both a link target and
 * an iframe source — so the same allowlist predicate is re-checked here.
 * Anything unapproved is reported as simply unconfigured, which callers render
 * as an empty state rather than an error.
 *
 * Reads through SiteSettings::current(), the existing read-only accessor: it
 * never writes on a GET and memoizes per request, so the layout composer and a
 * page controller asking in the same request cost one query between them.
 */
class BookingUrl
{
    public static function current(): ?string
    {
        $url = SiteSettings::current()?->cal_booking_url;

        return is_string($url) && ValidCalUrl::passes($url) ? $url : null;
    }
}
