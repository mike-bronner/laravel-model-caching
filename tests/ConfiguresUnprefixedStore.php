<?php

namespace GeneaLabs\LaravelModelCaching\Tests;

// Adds a Redis cache store whose keys carry no prefix at all, on a database of
// its own. The configuration has to exist before the application boots, because
// the Redis manager copies its connection list when it is first resolved.
trait ConfiguresUnprefixedStore
{
    private const UNPREFIXED_DATABASE = 3;

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app["config"]->set("database.redis.model-cache-unprefixed", [
            "host" => env("REDIS_HOST", "127.0.0.1"),
            "password" => env("REDIS_PASSWORD", null),
            "port" => env("REDIS_PORT", 6379),
            "database" => self::UNPREFIXED_DATABASE,
        ]);
        $app["config"]->set("cache.stores.model-unprefixed", [
            "driver" => "redis",
            "connection" => "model-cache-unprefixed",
            "prefix" => "",
        ]);
    }
}
