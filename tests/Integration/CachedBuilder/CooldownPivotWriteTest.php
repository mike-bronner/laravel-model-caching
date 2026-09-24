<?php namespace GeneaLabs\LaravelModelCaching\Tests\Integration\CachedBuilder;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\BookWithCooldown;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Store;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;

class CooldownPivotWriteTest extends IntegrationTestCase
{
    private function startCooldownAndCacheBooks() : BookWithCooldown
    {
        (new BookWithCooldown)->withCacheCooldownSeconds(60)->get();
        (new BookWithCooldown)->orderBy("id")->pluck("title");

        $this->assertNotEmpty($this->booksTagEntrySets());

        return (new BookWithCooldown)->newQuery()->findOrFail(1);
    }

    private function booksTagEntrySets() : array
    {
        $token = (int) env("TEST_TOKEN", 1);

        return $this->scanRedisKeys(
            app("redis")->connection("model-cache"),
            "lmc-test-{$token}:tag:genealabs:laravel-model-caching:*:books:entries",
        );
    }

    private function newStoreId() : int
    {
        return Store::factory()->create()->id;
    }

    public function testAttachRespectsCooldown()
    {
        $storeId = $this->newStoreId();
        $book = $this->startCooldownAndCacheBooks();

        $book->stores()->attach($storeId);

        $this->assertNotEmpty($this->booksTagEntrySets());
    }

    public function testDetachRespectsCooldown()
    {
        $storeId = $this->newStoreId();
        (new BookWithCooldown)->newQuery()->findOrFail(1)->stores()->attach($storeId);
        $book = $this->startCooldownAndCacheBooks();

        $book->stores()->detach($storeId);

        $this->assertNotEmpty($this->booksTagEntrySets());
    }

    public function testSyncRespectsCooldown()
    {
        $storeId = $this->newStoreId();
        $book = $this->startCooldownAndCacheBooks();

        $book->stores()->sync([$storeId]);

        $this->assertNotEmpty($this->booksTagEntrySets());
    }

    public function testUpdateExistingPivotRespectsCooldown()
    {
        $storeId = $this->newStoreId();
        (new BookWithCooldown)->newQuery()->findOrFail(1)->stores()->attach($storeId);
        $book = $this->startCooldownAndCacheBooks();

        $book->stores()->updateExistingPivot($storeId, ["test" => "updated"]);

        $this->assertNotEmpty($this->booksTagEntrySets());
    }

    public function testPivotWriteWithoutCooldownStillFlushes()
    {
        $storeId = $this->newStoreId();
        (new BookWithCooldown)->orderBy("id")->pluck("title");
        $this->assertNotEmpty($this->booksTagEntrySets());

        (new BookWithCooldown)->newQuery()->findOrFail(1)->stores()->attach($storeId);

        $this->assertEmpty($this->booksTagEntrySets());
    }
}
