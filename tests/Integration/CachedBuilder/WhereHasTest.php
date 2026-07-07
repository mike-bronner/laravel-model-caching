<?php namespace GeneaLabs\LaravelModelCaching\Tests\Integration\CachedBuilder;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\BookWithUncachedStore;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;

class WhereHasTest extends IntegrationTestCase
{
    public function testWhereHasClause()
    {
        $authors = (new Author)
            ->whereHas("books")
            ->get();
        $uncachedAuthors = (new UncachedAuthor)
            ->whereHas("books")
            ->get();

        $this->assertEquals($authors->pluck("id"), $uncachedAuthors->pluck("id"));
    }

    public function testCountedWhereHasClause()
    {
        $authors = (new Author)
            ->whereHas("books", null, ">=", 2)
            ->get();
        $uncachedAuthors = (new UncachedAuthor)
            ->whereHas("books", null, ">=", 2)
            ->get();

        $this->assertEquals($authors->pluck("id"), $uncachedAuthors->pluck("id"));
    }

    public function testNestedWhereHasClauses()
    {
        $authors = (new Author)
            ->where("id", ">", 0)
            ->whereHas("books", function ($query) {
                $query->whereNull("description");
            })
            ->get();
        $uncachedAuthors = (new UncachedAuthor)
            ->where("id", ">", 0)
            ->whereHas("books", function ($query) {
                $query->whereNull("description");
            })
            ->get();

        $this->assertEquals($authors->pluck("id"), $uncachedAuthors->pluck("id"));
    }

    public function testNonCachedRelationshipPreventsCaching()
    {
        $book = (new BookWithUncachedStore)
            ->with("uncachedStores")
            ->whereHas("uncachedStores")
            ->get()
            ->first();
        $store = $book->uncachedStores->first();
        $store->name = "Waterstones";
        $store->save();
        $results = $this->cache()->tags([
                "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
                "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesuncachedstore",
                "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
            ])
            ->get(sha1(
                "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books:genealabslaravelmodelcachingtestsfixturesbook-exists-" .
                "and_books.id_=_book_store.book_id-testing:{$this->testingSqlitePath}testing.sqlite:uncachedStores"
            ));

        $this->assertNull($results);
    }
}
