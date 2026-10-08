<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Base test case for the application test suite, with a guard that
 * keeps database-refreshing tests away from any non-disposable database.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /** The only PostgreSQL database tests may rebuild (CLAUDE.md sections 6 and 13). */
    public const DISPOSABLE_POSTGRES_DATABASE = 'itour_testing';

    /**
     * Runs the database guard before RefreshDatabase (or any other trait)
     * touches the database. The guard lives here, not in a
     * beforeRefreshingDatabase() hook, because Pest applies RefreshDatabase
     * as a trait on each generated test class, and a trait method would
     * override a same-named hook declared on this parent class.
     *
     * @return array<class-string, class-string>
     */
    protected function setUpTraits()
    {
        $this->_assertDisposableTestDatabase();

        return parent::setUpTraits();
    }

    /**
     * RefreshDatabase runs migrate:fresh, which drops every table. Allow it
     * only on the isolated SQLite test database (phpunit.xml) or the
     * dedicated PostgreSQL database `itour_testing` — never the development,
     * staging, production, or any shared database.
     */
    private function _assertDisposableTestDatabase(): void
    {
        $strConnection = (string) config('database.default');
        $strDriver = (string) config("database.connections.{$strConnection}.driver");
        $strDatabase = (string) config("database.connections.{$strConnection}.database");

        if (! self::isDisposableTestDatabase($strDriver, $strDatabase)) {
            throw new RuntimeException(
                "Refusing to run tests against the '{$strDatabase}' {$strDriver} database. Tests may only use the "
                .'isolated SQLite test database or the disposable PostgreSQL database '.self::DISPOSABLE_POSTGRES_DATABASE.'.'
            );
        }
    }

    /**
     * Whether tests may rebuild this database: SQLite only in memory or as
     * the isolated test file (storage/testing.sqlite, phpunit.xml) — never
     * another SQLite file such as a developer's database/database.sqlite —
     * and PostgreSQL only as itour_testing.
     */
    public static function isDisposableTestDatabase(string $strDriver, string $strDatabase): bool
    {
        $blnIsIsolatedSqlite = $strDriver === 'sqlite'
            && ($strDatabase === ':memory:' || basename(str_replace('\\', '/', $strDatabase)) === 'testing.sqlite');
        $blnIsDisposablePostgres = $strDriver === 'pgsql' && $strDatabase === self::DISPOSABLE_POSTGRES_DATABASE;

        return $blnIsIsolatedSqlite || $blnIsDisposablePostgres;
    }
}
