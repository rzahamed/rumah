<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestDatabaseGuard;

/**
 * Proves the fail-fast database guard refuses every unsafe target and accepts
 * only a clearly named PostgreSQL test database. Pure unit tests — no app
 * boot, no database connection.
 */
class TestDatabaseGuardTest extends TestCase
{
    public function test_accepts_the_dedicated_postgres_test_database(): void
    {
        $this->expectNotToPerformAssertions();

        TestDatabaseGuard::check('pgsql', 'basecms_testing', null);
        TestDatabaseGuard::check('pgsql', 'basecms_testing', '');
        TestDatabaseGuard::check('pgsql', 'basecms_test', null);
    }

    public function test_refuses_sqlite_driver(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('[pgsql]');

        TestDatabaseGuard::check('sqlite', ':memory:', null);
    }

    public function test_refuses_the_development_database(): void
    {
        // The guard is intentionally generic: the development database is
        // refused by the suffix rule, not by a hardcoded starter name.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must end in `_testing` or `_test`');

        TestDatabaseGuard::check('pgsql', 'basecms', null);
    }

    public function test_refuses_an_empty_database_name(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('empty');

        TestDatabaseGuard::check('pgsql', '', null);
    }

    public function test_refuses_a_database_name_containing_whitespace(): void
    {
        // Malformed names are rejected, never trimmed into validity.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('whitespace');

        TestDatabaseGuard::check('pgsql', 'basecms_testing ', null);
    }

    public function test_refuses_a_database_without_test_suffix(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must end in `_testing` or `_test`');

        TestDatabaseGuard::check('pgsql', 'client_production', null);
    }

    public function test_refuses_a_non_empty_db_url_override(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DB_URL');

        TestDatabaseGuard::check('pgsql', 'basecms_testing', 'pgsql://root@10.0.0.5/other');
    }
}
