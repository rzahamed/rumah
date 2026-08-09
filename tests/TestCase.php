<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\TestDatabaseGuard;

abstract class TestCase extends BaseTestCase
{
    /**
     * Runs before any database testing trait (RefreshDatabase,
     * DatabaseTruncation, …) can migrate or truncate anything: inspects the
     * RESOLVED active connection and refuses to continue unless it is the
     * dedicated PostgreSQL test database. See Tests\Support\TestDatabaseGuard.
     */
    protected function setUpTraits(): array
    {
        $connection = $this->app->make('db')->connection();

        TestDatabaseGuard::check(
            $connection->getDriverName(),
            (string) $connection->getDatabaseName(),
            config('database.connections.'.$connection->getName().'.url'),
        );

        return parent::setUpTraits();
    }
}
