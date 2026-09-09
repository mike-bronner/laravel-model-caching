<?php namespace GeneaLabs\LaravelModelCaching\Tests\Integration\CachedBuilder;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\BookWithUncachedStore;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Profile;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;
use ReflectionMethod;

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

    private function cacheKey($query) : string
    {
        return (new ReflectionMethod($query, "makeCacheKey"))
            ->invoke($query);
    }

    /**
     * Assert the query cached its result under exactly the given tags.
     *
     * An emptied result after a write is not evidence on its own: a query that
     * stopped caching altogether returns the same thing. Laravel's TagSet
     * namespaces an entry by the tags it was written with, so reading it back
     * under the expected list is what pins the tag actually used.
     */
    private function assertCachedUnderTags($query, array $tags) : void
    {
        $key = sha1($this->cacheKey($query));
        $query->get();

        $this->assertNotNull(
            $this->cache()
                ->tags($tags)
                ->get($key),
            "The query was not cached under the expected tags.",
        );
    }

    private function tag(string $name) : string
    {
        return "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:{$name}";
    }

    public function testWhereHasCacheIsBustedWhenRelatedTableIsWritten()
    {
        $author = Author::factory()->create(["name" => "John"]);
        Book::factory()->create(["author_id" => $author->id]);

        $this->assertCachedUnderTags(
            (new Book)->whereHas("author", function ($query) {
                $query->where("name", "John");
            }),
            [
                $this->tag("genealabslaravelmodelcachingtestsfixturesbook"),
                $this->tag("books"),
                $this->tag("authors"),
            ],
        );

        $query = fn () => (new Book)
            ->whereHas("author", function ($query) {
                $query->where("name", "John");
            })
            ->get();

        $this->assertNotEmpty($query());

        $author->name = "Jane";
        $author->save();

        $this->assertEmpty($query());
    }

    public function testNestedWhereHasCacheIsBustedWhenDeeplyRelatedTableIsWritten()
    {
        $author = Author::factory()->create(["name" => "John"]);
        $profile = Profile::factory()->create(["author_id" => $author->id, "first_name" => "Alpha"]);
        Book::factory()->create(["author_id" => $author->id]);

        // The `profiles` tag is the whole point: it is two relations away from
        // the queried model, and only the recursive walk reaches it.
        $this->assertCachedUnderTags(
            (new Book)->whereHas("author", function ($query) {
                $query->whereHas("profile", function ($query) {
                    $query->where("first_name", "Alpha");
                });
            }),
            [
                $this->tag("genealabslaravelmodelcachingtestsfixturesbook"),
                $this->tag("books"),
                $this->tag("authors"),
                $this->tag("profiles"),
            ],
        );

        $query = fn () => (new Book)
            ->whereHas("author", function ($query) {
                $query->whereHas("profile", function ($query) {
                    $query->where("first_name", "Alpha");
                });
            })
            ->get();

        $this->assertNotEmpty($query());

        $profile->first_name = "Beta";
        $profile->save();

        $this->assertEmpty($query());
    }
}
