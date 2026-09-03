<?php namespace GeneaLabs\LaravelModelCaching\Tests\Integration\CachedBuilder;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;
use ReflectionMethod;

// whereBetweenColumns() carries no "operator", so the cache key builder used to
// read a key that is not there and raise "Undefined array key". Under Laravel's
// default error handler that converts to an ErrorException, so the query threw
// instead of returning rows.
class WhereBetweenColumnsTest extends IntegrationTestCase
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

    public function testWhereBetweenColumnsProducesACacheKeyWithoutRaisingAWarning()
    {
        $query = (new Book)->whereBetweenColumns("published_at", ["created_at", "updated_at"]);

        $this->assertEquals(
            $this->keyPrefix() . "-published_at_betweencolumns_created_at_updated_at",
            $this->cacheKey($query)
        );
    }

    public function testWhereNotBetweenColumnsProducesADifferentCacheKey()
    {
        $between = (new Book)->whereBetweenColumns("published_at", ["created_at", "updated_at"]);
        $notBetween = (new Book)->whereNotBetweenColumns("published_at", ["created_at", "updated_at"]);

        $this->assertEquals(
            $this->keyPrefix() . "-published_at_betweencolumns_created_at_updated_at",
            $this->cacheKey($between)
        );
        $this->assertEquals(
            $this->keyPrefix() . "-published_at_not_betweencolumns_created_at_updated_at",
            $this->cacheKey($notBetween)
        );
    }

    public function testWhereBetweenColumnsReturnsRowsRatherThanThrowing()
    {
        $results = (new Book)
            ->whereBetweenColumns("published_at", ["created_at", "updated_at"])
            ->get();
        $liveResults = (new UncachedBook)
            ->whereBetweenColumns("published_at", ["created_at", "updated_at"])
            ->get();

        $this->assertEquals($liveResults->pluck("id"), $results->pluck("id"));
    }

    public function testWhereNotBetweenColumnsReturnsItsOwnResultsAfterWhereBetweenColumnsWasCached()
    {
        $betweenQuery = (new Book)
            ->whereBetweenColumns("published_at", ["created_at", "updated_at"]);
        $betweenKey = sha1($this->cacheKey($betweenQuery));
        $betweenResults = $betweenQuery->get();
        $tags = [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
        ];

        $cached = $this->cache()
            ->tags($tags)
            ->get($betweenKey);

        $this->assertNotNull(
            $cached,
            "The first query must populate the cache, or the second query has nothing to collide with"
        );
        $this->assertEquals($betweenResults->pluck("id"), $cached["value"]->pluck("id"));

        $results = (new Book)
            ->whereNotBetweenColumns("published_at", ["created_at", "updated_at"])
            ->get();
        $liveResults = (new UncachedBook)
            ->whereNotBetweenColumns("published_at", ["created_at", "updated_at"])
            ->get();

        $this->assertNotEmpty($results);
        $this->assertEquals($liveResults->pluck("id"), $results->pluck("id"));
    }
}
