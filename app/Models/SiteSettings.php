<?php

namespace App\Models;

use App\Rules\ValidCalUrl;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Single-row site settings (row cardinality enforced by the database: a
 * unique index plus a CHECK constraint on `singleton`). The four
 * custom-code snippets are trusted raw HTML: only an ACTIVE super admin
 * may read or write them through the panel (the 'manage-site-settings'
 * Gate ability is granted exclusively by the super-admin Gate::before
 * override), and they are output raw at exactly four positions in the
 * PUBLIC layout — never on the admin host. Snippet contents are never
 * logged (no activity logging on this model) and never serialized
 * (hidden), so they cannot leak through JSON responses. `singleton` is
 * fillable only for the controlled instance() creation path — it is never
 * an admin form field.
 */
#[Fillable(['singleton', 'custom_head_start', 'custom_head_end', 'custom_body_start', 'custom_body_end', 'cal_booking_url'])]
class SiteSettings extends Model
{
    /**
     * The four public-layout injection positions, in document order.
     *
     * @var list<string>
     */
    public const array CUSTOM_CODE_POSITIONS = [
        'custom_head_start',
        'custom_head_end',
        'custom_body_start',
        'custom_body_end',
    ];

    /** Per-snippet size limit, enforced in the form AND at persistence. */
    public const int SNIPPET_MAX_LENGTH = 20000;

    /**
     * Snippets never appear in any serialized representation of the model.
     *
     * @var list<string>
     */
    protected $hidden = [
        'custom_head_start',
        'custom_head_end',
        'custom_body_start',
        'custom_body_end',
    ];

    /**
     * Persistence-path guards, independent of Filament validation.
     */
    protected static function booted(): void
    {
        static::saving(function (SiteSettings $settings): void {
            foreach (self::CUSTOM_CODE_POSITIONS as $position) {
                $value = $settings->getAttribute($position);

                if ($value !== null && ! is_string($value)) {
                    throw new InvalidArgumentException(
                        "Custom code snippet [{$position}] must be a string or null."
                    );
                }

                if (is_string($value) && mb_strlen($value) > self::SNIPPET_MAX_LENGTH) {
                    throw new InvalidArgumentException(
                        "Custom code snippet [{$position}] exceeds ".self::SNIPPET_MAX_LENGTH.' characters.'
                    );
                }
            }

            $calUrl = $settings->getAttribute('cal_booking_url');

            if ($calUrl !== null && (
                ! is_string($calUrl)
                || ($calUrl !== '' && ! ValidCalUrl::passes($calUrl))
            )) {
                throw new InvalidArgumentException(
                    'cal_booking_url must be an HTTPS URL on an approved Cal.com host.'
                );
            }
        });
    }

    /**
     * The single settings row, created on first use (admin write path).
     */
    public static function instance(): self
    {
        return self::query()->firstOrCreate(['singleton' => true]);
    }

    /**
     * Read-only accessor for the public path: never writes on a GET.
     */
    public static function current(): ?self
    {
        return self::query()->where('singleton', true)->first();
    }
}
