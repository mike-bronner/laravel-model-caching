<?php

namespace GeneaLabs\LaravelModelCaching\Tests;

use PDO;
use Throwable;

// A test file that runs against PostgreSQL, which SQLite cannot stand in for,
// uses this trait. It points the default connection at PostgreSQL before the
// application boots, because RefreshDatabase migrates the default connection
// during setUp().
//
// Pest has no hook that runs before the parent setUp(), so the reachability
// guard runs here, at the start of building the application. That is still
// before RefreshDatabase first opens the connection, so a missing server skips
// the test instead of failing it. A missing pdo_pgsql driver throws in the
// probe too, which is the same answer: this test cannot run.
trait RunsOnPostgres
{
    protected function getEnvironmentSetUp($app)
    {
        $this->skipWithoutPostgres();

        parent::getEnvironmentSetUp($app);

        $app['config']->set('database.default', 'pgsql');
        $app['config']->set('database.connections.pgsql.host', env("PGSQL_HOST", "pgsql"));
        $app['config']->set('database.connections.pgsql.database', env("PGSQL_DATABASE", "testing"));
        $app['config']->set('database.connections.pgsql.username', env("PGSQL_USERNAME", "forge"));
        $app['config']->set('database.connections.pgsql.password', env("PGSQL_PASSWORD", "secret"));
    }

    // Probed with a bare PDO rather than through the container, because the
    // container is not built yet at this point.
    private function skipWithoutPostgres() : void
    {
        $host = env("PGSQL_HOST", "pgsql");
        $database = env("PGSQL_DATABASE", "testing");

        try {
            new PDO(
                "pgsql:host={$host};dbname={$database}",
                env("PGSQL_USERNAME", "forge"),
                env("PGSQL_PASSWORD", "secret"),
                [PDO::ATTR_TIMEOUT => 3],
            );
        } catch (Throwable $exception) {
            $this->markTestSkipped(
                "No reachable PostgreSQL server: {$exception->getMessage()}"
            );
        }
    }
}
