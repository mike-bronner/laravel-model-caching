<?php namespace GeneaLabs\LaravelModelCaching\Tests\Integration\CachedBuilder;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;

class ForwardedWriteInvalidationTest extends IntegrationTestCase
{
    private function newBookRow(string $title) : array
    {
        $book = (new UncachedBook)->findOrFail(1);

        return [
            "author_id" => $book->author_id,
            "publisher_id" => $book->publisher_id,
            "published_at" => $book->published_at,
            "title" => $title,
            "price" => 1,
        ];
    }

    private function assertCachedTitlesMatchDatabase(callable $write) : void
    {
        $before = (new Book)->orderBy("id")->pluck("title");

        $write();

        $after = (new Book)->orderBy("id")->pluck("title");
        $live = (new UncachedBook)->orderBy("id")->pluck("title");

        $this->assertNotEquals($before, $live, "The write must change what the database returns");
        $this->assertEquals($live, $after);
    }

    public function testUpsertInvalidatesCache()
    {
        $this->assertCachedTitlesMatchDatabase(function () {
            $row = ["id" => 1] + $this->newBookRow("upserted");

            Book::upsert([$row], ["id"], ["title"]);
        });
    }

    public function testInsertOrIgnoreInvalidatesCache()
    {
        $this->assertCachedTitlesMatchDatabase(function () {
            Book::insertOrIgnore([$this->newBookRow("inserted or ignored")]);
        });
    }

    public function testFillAndInsertOrIgnoreInvalidatesCache()
    {
        $this->assertCachedTitlesMatchDatabase(function () {
            Book::fillAndInsertOrIgnore([$this->newBookRow("filled and inserted")]);
        });
    }

    public function testInsertOrIgnoreReturningInvalidatesCache()
    {
        $this->assertCachedTitlesMatchDatabase(function () {
            Book::insertOrIgnoreReturning([$this->newBookRow("inserted returning")], ["id"]);
        });
    }

    public function testInsertGetIdInvalidatesCache()
    {
        $this->assertCachedTitlesMatchDatabase(function () {
            Book::insertGetId($this->newBookRow("inserted with id"));
        });
    }

    public function testInsertUsingInvalidatesCache()
    {
        $this->assertCachedTitlesMatchDatabase(function () {
            Book::insertUsing(
                ["author_id", "publisher_id", "published_at", "title", "price"],
                (new UncachedBook)
                    ->selectRaw("author_id, publisher_id, published_at, 'copied', price")
                    ->where("id", 1),
            );
        });
    }

    public function testInsertOrIgnoreUsingInvalidatesCache()
    {
        $this->assertCachedTitlesMatchDatabase(function () {
            Book::insertOrIgnoreUsing(
                ["author_id", "publisher_id", "published_at", "title", "price"],
                (new UncachedBook)
                    ->selectRaw("author_id, publisher_id, published_at, 'copied or ignored', price")
                    ->where("id", 1),
            );
        });
    }

    public function testUpdateOrInsertInvalidatesCache()
    {
        $this->assertCachedTitlesMatchDatabase(function () {
            Book::updateOrInsert(["id" => 1], ["title" => "updated or inserted"]);
        });
    }

    // Creating a model runs insertGetId() underneath. With model events off,
    // as under saveQuietly(), that write is the only thing left to invalidate.
    public function testQuietCreateInvalidatesCache()
    {
        $this->assertCachedTitlesMatchDatabase(function () {
            (new Book)->fill($this->newBookRow("created quietly"))->saveQuietly();
        });
    }

    public function testTouchInvalidatesCache()
    {
        $before = (new Book)->findOrFail(1)->updated_at;

        $this->travel(1)->hours();
        (new Book)->where("id", 1)->touch();

        $after = (new Book)->findOrFail(1)->updated_at;
        $live = (new UncachedBook)->findOrFail(1)->updated_at;

        $this->assertNotEquals($before, $live);
        $this->assertEquals($live, $after);
    }

    public function testIncrementEachInvalidatesCache()
    {
        $before = (new Book)->findOrFail(1)->price;

        (new Book)->where("id", 1)->incrementEach(["price" => 2]);

        $this->assertEquals($before + 2, (new Book)->findOrFail(1)->price);
    }

    public function testDecrementEachInvalidatesCache()
    {
        $before = (new Book)->findOrFail(1)->price;

        (new Book)->where("id", 1)->decrementEach(["price" => 2]);

        $this->assertEquals($before - 2, (new Book)->findOrFail(1)->price);
    }
}
