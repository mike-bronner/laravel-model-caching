<?php namespace GeneaLabs\LaravelModelCaching\Tests\Integration\CachedBuilder;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;
use ReflectionMethod;

// Every case here is a query whose only difference from its affirmative twin is
// a negation — carried either by the clause's boolean ("and not") or by a
// separate "not" flag on a shared clause type. Both used to be dropped from the
// cache key, so the negated query read the cached rows of the query returning
// exactly what it excludes.
//
// Book is deliberately the fixture: Author carries a SoftDeletes global scope,
// and Eloquent's addNewWheresWithinGroup() rewraps existing clauses in a nested
// group as soon as a non-"and" boolean appears, which shifts the key for
// reasons that have nothing to do with the boolean reaching it.
class WhereNotTest extends IntegrationTestCase
{
    private function cacheKey($query) : string
    {
        return (new ReflectionMethod($query, "makeCacheKey"))
            ->invoke($query);
    }

    private function keyPrefix() : string
    {
        return "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite"
            . ":books:genealabslaravelmodelcachingtestsfixturesbook";
    }

    private function bookTags() : array
    {
        return [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
        ];
    }

    public function testWhereNotProducesDifferentCacheKeyThanWhere()
    {
        $where = (new Book)->where("id", 1);
        $whereNot = (new Book)->whereNot("id", 1);

        $this->assertEquals(
            $this->keyPrefix() . "-id_=_1",
            $this->cacheKey($where)
        );
        $this->assertEquals(
            $this->keyPrefix() . "-and_not-id_=_1",
            $this->cacheKey($whereNot)
        );
    }

    public function testWhereNotReturnsItsOwnResultsAfterWhereWasCached()
    {
        $whereQuery = (new Book)->where("id", 1);
        $whereKey = sha1($this->cacheKey($whereQuery));
        $whereResults = $whereQuery->get();

        $cachedWhereResults = $this->cache()
            ->tags($this->bookTags())
            ->get($whereKey);

        $this->assertNotNull(
            $cachedWhereResults,
            "The first query must populate the cache, or the second query has nothing to collide with"
        );
        $this->assertEquals(
            $whereResults->pluck("id"),
            $cachedWhereResults["value"]->pluck("id")
        );

        $results = (new Book)->whereNot("id", 1)->get();
        $liveResults = (new UncachedBook)->whereNot("id", 1)->get();

        $this->assertEquals($liveResults->pluck("id"), $results->pluck("id"));
        $this->assertNotContains(1, $results->pluck("id")->toArray());
    }

    public function testWhereNotBetweenProducesDifferentCacheKeyThanWhereBetween()
    {
        $between = (new Book)->whereBetween("id", [1, 2]);
        $notBetween = (new Book)->whereNotBetween("id", [1, 2]);

        $this->assertEquals(
            $this->keyPrefix() . "-id_between_1_2",
            $this->cacheKey($between)
        );
        $this->assertEquals(
            $this->keyPrefix() . "-id_not_between_1_2",
            $this->cacheKey($notBetween)
        );
    }

    public function testWhereNotBetweenReturnsItsOwnResultsAfterWhereBetweenWasCached()
    {
        $betweenQuery = (new Book)->whereBetween("id", [1, 2]);
        $betweenKey = sha1($this->cacheKey($betweenQuery));
        $betweenResults = $betweenQuery->get();

        $cachedBetweenResults = $this->cache()
            ->tags($this->bookTags())
            ->get($betweenKey);

        $this->assertNotNull(
            $cachedBetweenResults,
            "The first query must populate the cache, or the second query has nothing to collide with"
        );
        $this->assertEquals(
            $betweenResults->pluck("id"),
            $cachedBetweenResults["value"]->pluck("id")
        );

        $results = (new Book)->whereNotBetween("id", [1, 2])->get();
        $liveResults = (new UncachedBook)->whereNotBetween("id", [1, 2])->get();

        $this->assertEquals($liveResults->pluck("id"), $results->pluck("id"));
        $this->assertEmpty(array_intersect([1, 2], $results->pluck("id")->toArray()));
    }

    // The JSON families need a real JSON column, which only the authors table
    // has. Author's SoftDeletes global scope is harmless here — it adds one
    // fixed clause to the key, and unlike the "or" cases it never triggers
    // addNewWheresWithinGroup(), because a "not" flag is not a boolean.
    private function authorKeyPrefix() : string
    {
        return "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite"
            . ":authors:genealabslaravelmodelcachingtestsfixturesauthor";
    }

    private function authorTags() : array
    {
        return [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
        ];
    }

    // whereJsonContains() gets no result-level twin: SQLite cannot compile a
    // JSON "contains" predicate at all, and the suite's PostgreSQL-backed JSON
    // tests are skipped unless that server is running. The key is built without
    // touching the database, and the key is where the defect lived.
    public function testWhereJsonDoesntContainProducesDifferentCacheKeyThanWhereJsonContains()
    {
        $contains = (new Author)->whereJsonContains("finances->total", 5000);
        $doesntContain = (new Author)->whereJsonDoesntContain("finances->total", 5000);

        $this->assertEquals(
            $this->authorKeyPrefix() . "-finances->total_jsoncontains_5000-authors.deleted_at_null",
            $this->cacheKey($contains)
        );
        $this->assertEquals(
            $this->authorKeyPrefix() . "-finances->total_not_jsoncontains_5000-authors.deleted_at_null",
            $this->cacheKey($doesntContain)
        );
    }

    public function testWhereJsonDoesntContainKeyProducesDifferentCacheKeyThanWhereJsonContainsKey()
    {
        $containsKey = (new Author)->whereJsonContainsKey("finances->total");
        $doesntContainKey = (new Author)->whereJsonDoesntContainKey("finances->total");

        $this->assertEquals(
            $this->authorKeyPrefix() . "-finances->total_jsoncontainskey_-authors.deleted_at_null",
            $this->cacheKey($containsKey)
        );
        $this->assertEquals(
            $this->authorKeyPrefix() . "-finances->total_not_jsoncontainskey_-authors.deleted_at_null",
            $this->cacheKey($doesntContainKey)
        );
    }

    public function testWhereJsonContainsKeyReturnsItsOwnResultsAfterWhereJsonDoesntContainKeyWasCached()
    {
        $doesntContainKeyQuery = (new Author)->whereJsonDoesntContainKey("finances->total");
        $doesntContainKeyKey = sha1($this->cacheKey($doesntContainKeyQuery));
        $doesntContainKeyResults = $doesntContainKeyQuery->get();

        $cachedResults = $this->cache()
            ->tags($this->authorTags())
            ->get($doesntContainKeyKey);

        $this->assertNotNull(
            $cachedResults,
            "The first query must populate the cache, or the second query has nothing to collide with"
        );
        $this->assertEquals(
            $doesntContainKeyResults->pluck("id"),
            $cachedResults["value"]->pluck("id")
        );

        $results = (new Author)->whereJsonContainsKey("finances->total")->get();
        $liveResults = (new UncachedAuthor)->whereJsonContainsKey("finances->total")->get();

        $this->assertNotEmpty($results);
        $this->assertEmpty($doesntContainKeyResults);
        $this->assertEquals($liveResults->pluck("id"), $results->pluck("id"));
    }
}
