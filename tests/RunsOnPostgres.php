<?php

namespace GeneaLabs\LaravelModelCaching\Tests;

use PDO;
use Throwable;

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
