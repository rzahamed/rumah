<?php

namespace App\Support;

use App\Models\NewsletterSubscriber;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streamed CSV export of the subscriber list.
 *
 * Streamed, not buffered: rows are fetched with chunkById() and written
 * straight to the output handle, so memory stays flat no matter how long
 * the list grows.
 *
 * Spreadsheet-safe: every cell whose first character can start a formula
 * (= + - @) or a field-splitting control character (TAB, CR, LF) is
 * prefixed with a single quote. This is not theoretical for this data —
 * "+" and "=" are legal in an email local part, so a crafted address is the
 * realistic path to a formula executing when an administrator opens the
 * file.
 *
 * CSV parameters are always explicit. PHP 8.4 deprecates relying on
 * fputcsv()'s default escape argument, and the historical default also
 * emits non-RFC-4180 backslash escaping; an empty escape string gives
 * plain, portable quoting.
 *
 * Authorization is the caller's responsibility (the Filament action
 * declares ->authorize('export')); nothing here decides who may download.
 */
class NewsletterCsvExport
{
    private const CHUNK = 500;

    private const DELIMITER = ',';

    private const ENCLOSURE = '"';

    /** RFC-4180 quoting: no escape character. */
    private const ESCAPE = '';

    /** Characters that make a spreadsheet treat a cell as something other than text. */
    private const RISKY_PREFIXES = ['=', '+', '-', '@', "\t", "\r", "\n"];

    public static function filename(): string
    {
        return 'newsletter-subscribers-'.now()->format('Y-m-d').'.csv';
    }

    public static function stream(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM: without it Excel reads the Arabic locale values as
            // mojibake.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv(
                $handle,
                ['email', 'locale', 'consented_at', 'created_at'],
                self::DELIMITER,
                self::ENCLOSURE,
                self::ESCAPE,
            );

            NewsletterSubscriber::query()
                ->orderBy('id')
                ->chunkById(self::CHUNK, function ($subscribers) use ($handle): void {
                    foreach ($subscribers as $subscriber) {
                        fputcsv(
                            $handle,
                            [
                                self::safe($subscriber->email),
                                self::safe($subscriber->locale),
                                $subscriber->consented_at?->toIso8601String(),
                                $subscriber->created_at?->toIso8601String(),
                            ],
                            self::DELIMITER,
                            self::ENCLOSURE,
                            self::ESCAPE,
                        );
                    }

                    flush();
                });

            fclose($handle);
        }, self::filename(), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Neutralize a cell that a spreadsheet would otherwise interpret.
     */
    private static function safe(?string $value): string
    {
        $value = (string) $value;

        if ($value === '') {
            return $value;
        }

        return in_array($value[0], self::RISKY_PREFIXES, true) ? "'".$value : $value;
    }
}
