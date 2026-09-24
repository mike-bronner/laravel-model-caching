<?php namespace GeneaLabs\LaravelModelCaching\Tests\Integration\Console\Commands;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorWithCooldown;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;

class FlushScopeTest extends IntegrationTestCase
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

    public function testClearKeepsApplicationEntriesUnderTheSameStorePrefix()
    {
        $store = app("cache")->store("model");
        $store->forever("application:settings", "kept");
        $store->tags(["application-tag"])->forever("application:tagged", "kept");

        $this->assertStaleCacheIsCleared();

        $this->assertSame("kept", $store->get("application:settings"));
        $this->assertSame("kept", $store->tags(["application-tag"])->get("application:tagged"));
    }

    public function testClearRemovesEveryPackageKey()
    {
        (new Author)->with("books")->get();
        (new AuthorWithCooldown)->withCacheCooldownSeconds(30)->get();

        $this->assertNotEmpty($this->packageKeys());

        $this->artisan("modelCache:clear")->assertExitCode(0);

        $this->assertSame([], $this->packageKeys());
    }

    public function testClearOnUnprefixedStoreKeepsApplicationKeys()
    {
        config(["laravel-model-caching.store" => "model-unprefixed"]);
        $connection = app("redis")->connection("model-cache-unprefixed");
        $applicationKey = "application:unprefixed:" . uniqid();
        $connection->set($applicationKey, "kept");

        try {
            $this->assertStaleCacheIsCleared();

            $this->assertSame("kept", $connection->get($applicationKey));
        } finally {
            $connection->del($applicationKey);
            $this->artisan("modelCache:clear");
        }
    }

    private function assertStaleCacheIsCleared() : void
    {
        $authorId = (new Author)->get()->first()->id;
        (new UncachedAuthor)->where("id", $authorId)->update(["name" => "CLEARED_AUTHOR"]);

        $this->assertNotSame("CLEARED_AUTHOR", (new Author)->get()->firstWhere("id", $authorId)->name);

        $this->artisan("modelCache:clear")->assertExitCode(0);

        $this->assertSame("CLEARED_AUTHOR", (new Author)->get()->firstWhere("id", $authorId)->name);
    }

    private function packageKeys() : array
    {
        $token = (int) env("TEST_TOKEN", 1);

        return $this->scanRedisKeys(app("redis")->connection("model-cache"), "lmc-test-{$token}:*");
    }
}
