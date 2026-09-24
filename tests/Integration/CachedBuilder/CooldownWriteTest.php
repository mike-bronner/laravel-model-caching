<?php namespace GeneaLabs\LaravelModelCaching\Tests\Integration\CachedBuilder;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\BookWithCooldown;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;

class CooldownWriteTest extends IntegrationTestCase
{
    private function startCooldown() : void
    {
        (new BookWithCooldown)
            ->withCacheCooldownSeconds(60)
            ->get();
    }

    private function cachedPrices() : array
    {
        return (new BookWithCooldown)->orderBy("id")->pluck("price", "id")->all();
    }

    private function livePrices() : array
    {
        return (new UncachedBook)->orderBy("id")->pluck("price", "id")->all();
    }

    private function assertWriteKeepsCacheDuringCooldown(callable $write) : void
    {
        $this->startCooldown();
        $before = $this->cachedPrices();

        $write();

        $this->assertNotEquals($before, $this->livePrices(), "The write must change what the database returns");
        $this->assertEquals($before, $this->cachedPrices());
    }

    public function testIncrementRespectsCooldown()
    {
        $this->assertWriteKeepsCacheDuringCooldown(function () {
            (new BookWithCooldown)->where("id", 1)->increment("price", 5);
        });
    }

    public function testModelIncrementRespectsCooldown()
    {
        $this->assertWriteKeepsCacheDuringCooldown(function () {
            (new BookWithCooldown)->newQuery()->findOrFail(1)->increment("price", 5);
        });
    }

    public function testDecrementRespectsCooldown()
    {
        $this->assertWriteKeepsCacheDuringCooldown(function () {
            (new BookWithCooldown)->where("id", 1)->decrement("price", 5);
        });
    }

    public function testDeleteRespectsCooldown()
    {
        $this->assertWriteKeepsCacheDuringCooldown(function () {
            (new BookWithCooldown)->where("id", 1)->delete();
        });
    }

    public function testForceDeleteRespectsCooldown()
    {
        $this->assertWriteKeepsCacheDuringCooldown(function () {
            (new BookWithCooldown)->where("id", 1)->forceDelete();
        });
    }

    public function testTruncateRespectsCooldown()
    {
        $this->assertWriteKeepsCacheDuringCooldown(function () {
            (new BookWithCooldown)->newQuery()->truncate();
        });
    }

    public function testDestroyRespectsCooldown()
    {
        $this->assertWriteKeepsCacheDuringCooldown(function () {
            BookWithCooldown::destroy(1);
        });
    }

    public function testUpdateRespectsCooldown()
    {
        $this->assertWriteKeepsCacheDuringCooldown(function () {
            (new BookWithCooldown)->where("id", 1)->update(["price" => 12345]);
        });
    }

    public function testUpsertRespectsCooldown()
    {
        $this->assertWriteKeepsCacheDuringCooldown(function () {
            $book = (new UncachedBook)->findOrFail(1);

            BookWithCooldown::upsert(
                [[
                    "id" => 1,
                    "author_id" => $book->author_id,
                    "publisher_id" => $book->publisher_id,
                    "published_at" => $book->published_at,
                    "title" => $book->title,
                    "price" => 12345,
                ]],
                ["id"],
                ["price"],
            );
        });
    }

    public function testIncrementEachRespectsCooldown()
    {
        $this->assertWriteKeepsCacheDuringCooldown(function () {
            (new BookWithCooldown)->where("id", 1)->incrementEach(["price" => 5]);
        });
    }

    public function testForwardedWriteRespectsCooldown()
    {
        $this->assertWriteKeepsCacheDuringCooldown(function () {
            BookWithCooldown::updateOrInsert(["id" => 1], ["price" => 12345]);
        });
    }

    public function testWriteAfterCooldownExpiresFlushes()
    {
        $this->startCooldown();
        $this->cachedPrices();
        $this->assertNotEmpty($this->booksTagEntrySets());

        $this->travel(61)->seconds();
        (new BookWithCooldown)->where("id", 1)->increment("price", 5);

        $this->assertEmpty($this->booksTagEntrySets());
    }

    private function booksTagEntrySets() : array
    {
        $token = (int) env("TEST_TOKEN", 1);

        return $this->scanRedisKeys(
            app("redis")->connection("model-cache"),
            "lmc-test-{$token}:tag:genealabs:laravel-model-caching:*:books:entries",
        );
    }

    public function testReadAfterCooldownExpiresFlushesWritesMadeDuringIt()
    {
        $this->startCooldown();
        $this->cachedPrices();
        (new BookWithCooldown)->where("id", 1)->increment("price", 5);

        $this->travel(61)->seconds();

        $this->assertEquals($this->livePrices(), $this->cachedPrices());
    }
}
