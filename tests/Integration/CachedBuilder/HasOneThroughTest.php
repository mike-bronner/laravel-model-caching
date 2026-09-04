<?php namespace GeneaLabs\LaravelModelCaching\Tests\Integration\CachedBuilder;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Supplier;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedSupplier;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;

class HasOneThroughTest extends IntegrationTestCase
{
    public function testEagerloadedHasOneThrough()
    {
        $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:suppliers:genealabslaravelmodelcachingtestsfixturessupplier-testing:{$this->testingSqlitePath}testing.sqlite:history-limit_1");
        $tags = [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturessupplier",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixtureshistory",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:suppliers",
        ];

        $history = (new Supplier)
            ->with("history")
            ->first()
            ->history;
        $cachedResults = $this->cache()
            ->tags($tags)
            ->get($key)['value']
            ->first()
            ->history;
        $liveResults = (new UncachedSupplier)
            ->with("history")
            ->first()
            ->history;

        $this->assertEquals($liveResults->id, $history->id);
        $this->assertEquals($liveResults->id, $cachedResults->id);
        $this->assertNotEmpty($history);
        $this->assertNotEmpty($cachedResults);
        $this->assertNotEmpty($liveResults);
    }

    public function testLazyloadedHasOneThrough()
    {
        $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:histories:genealabslaravelmodelcachingtestsfixtureshistory-join_inner_users_1f027b356718-users.supplier_id_=_1-first");
        $tags = [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixtureshistory",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:histories",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:users",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesuser",
        ];

        $history = (new Supplier)
            ->first()
            ->history;
        $cachedResults = $this->cache()
            ->tags($tags)
            ->get($key)['value'];
        $liveResults = (new UncachedSupplier)
            ->first()
            ->history;

        $this->assertEquals($liveResults->id, $history->id);
        $this->assertEquals($liveResults->id, $cachedResults->id);
        $this->assertNotEmpty($history);
        $this->assertNotEmpty($cachedResults);
        $this->assertNotEmpty($liveResults);
    }
}