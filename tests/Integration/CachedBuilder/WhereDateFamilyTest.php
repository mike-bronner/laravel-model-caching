<?php namespace GeneaLabs\LaravelModelCaching\Tests\Integration\CachedBuilder;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;
use ReflectionMethod;

// whereDate(), whereDay(), whereMonth(), whereYear() and whereTime() all compile
// to "= ?" against the same column, so a key built from the operator alone
// cannot tell them apart — from each other, or from a plain where(). whereDay(5)
// and whereMonth(5) are the worst pair: Laravel zero-pads both bindings to "05",
// so the two keys matched byte for byte while the queries return unrelated rows.
//
// Book carries no global scope; Author's SoftDeletes scope adds clauses that
// would mask which segment of the key actually changed.
class WhereDateFamilyTest extends IntegrationTestCase
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

    private function assertFirstQueryWasCached($query, string $description) : void
    {
        $key = sha1($this->cacheKey($query));
        $results = $query->get();
        $cached = $this->cache()
            ->tags($this->bookTags())
            ->get($key);

        $this->assertNotNull(
            $cached,
            "{$description} must populate the cache, or the second query has nothing to collide with"
        );
        $this->assertEquals($results->pluck("id"), $cached["value"]->pluck("id"));
    }

    // This is the constraint the whole change is held to: a plain where() clause
    // renders by its operator alone, exactly as it always has, so no ordinary
    // query goes cold on upgrade. It is the only clause type with that
    // exemption, and this pins it.
    public function testPlainWhereKeyIsUnchanged()
    {
        $this->assertEquals(
            $this->keyPrefix() . "-published_at_=_2020%2D01%2D05",
            $this->cacheKey((new Book)->where("published_at", "=", "2020-01-05"))
        );
        $this->assertEquals(
            $this->keyPrefix() . "-id_=_5",
            $this->cacheKey((new Book)->where("id", "=", 5))
        );
        $this->assertEquals(
            $this->keyPrefix() . "-title_like_abc",
            $this->cacheKey((new Book)->where("title", "like", "abc"))
        );
    }

    public function testWhereDateProducesADifferentCacheKeyThanWhere()
    {
        $this->assertEquals(
            $this->keyPrefix() . "-published_at_=_2020%2D01%2D05",
            $this->cacheKey((new Book)->where("published_at", "=", "2020-01-05"))
        );
        $this->assertEquals(
            $this->keyPrefix() . "-published_at_date_=_2020%2D01%2D05",
            $this->cacheKey((new Book)->whereDate("published_at", "=", "2020-01-05"))
        );
    }

    public function testWhereDayProducesADifferentCacheKeyThanWhereMonth()
    {
        $this->assertEquals(
            $this->keyPrefix() . "-published_at_day_=_05",
            $this->cacheKey((new Book)->whereDay("published_at", 5))
        );
        $this->assertEquals(
            $this->keyPrefix() . "-published_at_month_=_05",
            $this->cacheKey((new Book)->whereMonth("published_at", 5))
        );
    }

    public function testWhereYearWhereTimeAndWhereProduceThreeDifferentCacheKeys()
    {
        $keys = [
            $this->cacheKey((new Book)->where("published_at", "=", 5)),
            $this->cacheKey((new Book)->whereYear("published_at", 5)),
            $this->cacheKey((new Book)->whereTime("published_at", "=", 5)),
        ];

        $this->assertEquals($this->keyPrefix() . "-published_at_=_5", $keys[0]);
        $this->assertEquals($this->keyPrefix() . "-published_at_year_=_5", $keys[1]);
        $this->assertEquals($this->keyPrefix() . "-published_at_time_=_5", $keys[2]);
        $this->assertCount(3, array_unique($keys));
    }

    public function testWhereDateReturnsItsOwnResultsAfterWhereWasCached()
    {
        $book = (new UncachedBook)->first();
        $book->published_at = "2020-01-05 13:45:00";
        $book->save();

        $this->assertFirstQueryWasCached(
            (new Book)->where("published_at", "=", "2020-01-05"),
            "The plain where-query"
        );

        $results = (new Book)
            ->whereDate("published_at", "=", "2020-01-05")
            ->get();
        $liveResults = (new UncachedBook)
            ->whereDate("published_at", "=", "2020-01-05")
            ->get();

        // The plain where() matches nothing — "2020-01-05" is not the stored
        // datetime — while whereDate() matches the row. Sharing a key served the
        // empty result for both.
        $this->assertCount(1, $results);
        $this->assertEquals($liveResults->pluck("id"), $results->pluck("id"));
    }

    public function testWhereMonthReturnsItsOwnResultsAfterWhereDayWasCached()
    {
        $book = (new UncachedBook)->first();
        $book->published_at = "2020-01-05 13:45:00";
        $book->save();

        $this->assertFirstQueryWasCached(
            (new Book)->whereDay("published_at", 5),
            "The whereDay-query"
        );

        $results = (new Book)->whereMonth("published_at", 5)->get();
        $liveResults = (new UncachedBook)->whereMonth("published_at", 5)->get();
        $dayResults = (new UncachedBook)->whereDay("published_at", 5)->get();

        $this->assertEquals($liveResults->pluck("id"), $results->pluck("id"));
        $this->assertNotEquals(
            $dayResults->pluck("id"),
            $results->pluck("id"),
            "The fixture must make the two queries return different rows, or this proves nothing"
        );
    }

    // Both queries bind the same value, 2020, which is the whole point: the
    // binding is the only thing the key used to carry, so the two shared it.
    // whereYear matches the row and whereTime matches nothing, so serving one
    // from the other's cache entry is observable.
    public function testWhereTimeReturnsItsOwnResultsAfterWhereYearWasCached()
    {
        $book = (new UncachedBook)->first();
        $book->published_at = "2020-01-05 13:45:00";
        $book->save();

        $yearQuery = (new Book)->whereYear("published_at", 2020);
        $timeQuery = (new Book)->whereTime("published_at", "=", 2020);

        $this->assertNotEquals(
            $this->cacheKey($yearQuery),
            $this->cacheKey($timeQuery),
            "Both bind 2020, so only the clause type can tell these keys apart"
        );

        $this->assertFirstQueryWasCached($yearQuery, "The whereYear-query");

        $results = $timeQuery->get();
        $liveResults = (new UncachedBook)
            ->whereTime("published_at", "=", 2020)
            ->get();
        $yearResults = (new UncachedBook)
            ->whereYear("published_at", 2020)
            ->get();

        $this->assertNotEmpty(
            $yearResults,
            "The fixture must make whereYear match something, or this proves nothing"
        );
        $this->assertEmpty($results);
        $this->assertEquals($liveResults->pluck("id"), $results->pluck("id"));
    }

    // Not the date family, but the same defect from the same cause: the type
    // was dropped, so the clause was named by its operator alone and matched a
    // plain where() on the same column and value.
    public function testWhereJsonLengthProducesADifferentCacheKeyThanWhere()
    {
        $this->assertEquals(
            $this->keyPrefix() . "-description_>_1",
            $this->cacheKey((new Book)->where("description", ">", 1))
        );
        $this->assertEquals(
            $this->keyPrefix() . "-description_jsonlength_>_1",
            $this->cacheKey((new Book)->whereJsonLength("description", ">", 1))
        );
    }

    public function testWhereSubProducesADifferentCacheKeyThanWhere()
    {
        $plain = $this->cacheKey((new Book)->where("author_id", "=", 1));
        $sub = $this->cacheKey(
            (new Book)->where("author_id", "=", function ($query) {
                $query->from("authors")
                    ->selectRaw("1");
            })
        );

        $this->assertEquals($this->keyPrefix() . "-author_id_=_1", $plain);
        $this->assertStringContainsString("-author_id_sub_=_", $sub);
        $this->assertNotEquals($plain, $sub);
    }
}
