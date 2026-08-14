<?php

namespace App\Models;

use Database\Factories\NewsletterSubscriberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A newsletter subscriber: PROTECTED ADMIN DATA created only by the public
 * endpoint, never through the panel. Admins may list, export and delete
 * individually — there is no update path. No requester metadata is stored
 * and the address is never written to the log.
 */
#[Fillable(['email', 'locale', 'consented_at'])]
class NewsletterSubscriber extends Model
{
    /** @use HasFactory<NewsletterSubscriberFactory> */
    use HasFactory;

    /**
     * Canonical form of a submitted address, applied BEFORE validation so
     * the validator, old() repopulation and persistence all see the same
     * value.
     *
     * Exactly three things happen and nothing else: non-string input is
     * turned into a value validation is guaranteed to reject, the value is
     * trimmed, and it is lowercased in full. No dot-stripping, no +tag
     * removal, no IDN/punycode conversion — those merge addresses that are
     * genuinely distinct.
     *
     * Lowercasing the local part departs from RFC 5321 case sensitivity.
     * That is deliberate: no mainstream provider honours the distinction,
     * and without it "A@x.com" and "a@x.com" become two subscribers for one
     * person, defeating the unique index.
     *
     * HOSTILE INPUT: arrays, nested arrays, objects and null all arrive
     * here from a crafted request. None may reach a string cast — an
     * array-to-string conversion raises a PHP warning, and warnings can
     * carry submitted data into the log. Non-strings return an empty
     * string, which fails 'required' and 'email'; rejecting it is
     * validation's job, not this method's, and nothing is logged either way.
     */
    public static function normalizeEmail(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        return mb_strtolower(trim($value), 'UTF-8');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'consented_at' => 'datetime',
        ];
    }
}
