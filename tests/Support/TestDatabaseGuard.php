<?php

namespace Tests\Support;

use Illuminate\Support\Str;
use RuntimeException;

/**
 * Fail-fast safety guard for the test suite's database.
 *
 * The suite migrates, truncates and rebuilds its database, so it must only
 * ever be pointed at a dedicated PostgreSQL test database. The values checked
 * here are taken from Laravel's RESOLVED active connection (not the raw env),
 * so anything that redirected the connection — an ambient DB_URL, a stray
 * .env — is caught before a single destructive operation runs.
 *
 * Intentionally generic: no hardcoded database names, so the guard remains
 * valid unchanged in every client repository created from this starter. The
 * suffix rule alone decides what a legitimate test database looks like, and
 * malformed names are rejected outright — never silently normalized.
 */
final class TestDatabaseGuard
{
    /**
     * @throws RuntimeException when the resolved connection is not a dedicated test database
     */
    public static function check(string $driver, string $database, ?string $configuredUrl): void
    {
        if ($driver !== 'pgsql') {
            throw new RuntimeException(sprintf(
                'Tests must run on the [pgsql] driver, got [%s]. PostgreSQL is the only supported database for this starter.',
                $driver
            ));
        }

        if ($configuredUrl !== null && $configuredUrl !== '') {
            throw new RuntimeException(
                'Tests refuse to run: DB_URL is set and would override the explicitly selected test database.'
            );
        }

        if ($database === '') {
            throw new RuntimeException(
                'Tests refuse to run: the resolved database name is empty. Configure the dedicated PostgreSQL test database explicitly.'
            );
        }

        if (preg_match('/\s/', $database) === 1) {
            throw new RuntimeException(sprintf(
                'Tests refuse to run: the resolved database name [%s] contains whitespace. Fix the configuration; names are never normalized.',
                $database
            ));
        }

        if (! Str::endsWith($database, ['_testing', '_test'])) {
            throw new RuntimeException(sprintf(
                'Tests refuse to run against database [%s]: the name must end in `_testing` or `_test` so destructive test operations can never reach a development or client database.',
                $database
            ));
        }
    }
}
