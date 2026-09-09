<?php namespace GeneaLabs\LaravelModelCaching\Tests\Integration\CachedBuilder;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;
use ReflectionMethod;

// An "or" clause widens a result set; its "and" twin narrows it. They used to
// share a cache key in every clause family except Basic, so the cheaper query
// answered for the stricter one.
//
// Book carries no global scope on purpose. Author uses SoftDeletes, and
// Eloquent's addNewWheresWithinGroup() wraps the pre-existing clauses in a
// nested group the moment an "or" appears, which moves the key on its own — a
// test written against Author cannot tell that apart from the boolean landing
// in the key.
class OrWhereTest extends IntegrationTestCase
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

    private function assertFirstQueryWasCached($query, string $description, array $extraTags = []) : void
    {
        $key = sha1($this->cacheKey($query));
        $results = $query->get();
        $cached = $this->cache()
            ->tags([...$this->bookTags(), ...$extraTags])
            ->get($key);

        $this->assertNotNull(
            $cached,
            "{$description} must populate the cache, or the second query has nothing to collide with"
        );
        $this->assertEquals($results->pluck("id"), $cached["value"]->pluck("id"));
    }

    public function testOrWhereProducesDifferentCacheKeyThanWhere()
    {
        $and = (new Book)->where("id", 1)->where("id", 2);
        $or = (new Book)->where("id", 1)->orWhere("id", 2);

        $this->assertEquals(
            $this->keyPrefix() . "-id_=_1-id_=_2",
            $this->cacheKey($and)
        );
        $this->assertEquals(
            $this->keyPrefix() . "-id_=_1-or-id_=_2",
            $this->cacheKey($or)
        );
    }

    public function testOrWhereReturnsItsOwnResultsAfterWhereWasCached()
    {
        $this->assertFirstQueryWasCached(
            (new Book)->where("id", 1)->where("id", 2),
            "The and-query"
        );

        $results = (new Book)->where("id", 1)->orWhere("id", 2)->get();
        $liveResults = (new UncachedBook)->where("id", 1)->orWhere("id", 2)->get();

        $this->assertEquals($liveResults->pluck("id"), $results->pluck("id"));
        $this->assertCount(2, $results);
    }

    public function testOrWhereClosureProducesDifferentCacheKeyThanWhereClosure()
    {
        $and = (new Book)
            ->where("id", 1)
            ->where(function ($query) {
                $query->where("id", 2);
            });
        $or = (new Book)
            ->where("id", 1)
            ->orWhere(function ($query) {
                $query->where("id", 2);
            });

        $this->assertEquals(
            $this->keyPrefix() . "-id_=_1-nested-id_=_2",
            $this->cacheKey($and)
        );
        $this->assertEquals(
            $this->keyPrefix() . "-id_=_1-or-nested-id_=_2",
            $this->cacheKey($or)
        );
    }

    public function testOrWhereClosureReturnsItsOwnResultsAfterWhereClosureWasCached()
    {
        $this->assertFirstQueryWasCached(
            (new Book)
                ->where("id", 1)
                ->where(function ($query) {
                    $query->where("id", 2);
                }),
            "The and-query"
        );

        $results = (new Book)
            ->where("id", 1)
            ->orWhere(function ($query) {
                $query->where("id", 2);
            })
            ->get();
        $liveResults = (new UncachedBook)
            ->where("id", 1)
            ->orWhere(function ($query) {
                $query->where("id", 2);
            })
            ->get();

        $this->assertEquals($liveResults->pluck("id"), $results->pluck("id"));
        $this->assertCount(2, $results);
    }

    public function testOrWhereHasProducesDifferentCacheKeyThanWhereHas()
    {
        $and = (new Book)
            ->where("id", 1)
            ->whereHas("author", function ($query) {
                $query->where("id", 2);
            });
        $or = (new Book)
            ->where("id", 1)
            ->orWhereHas("author", function ($query) {
                $query->where("id", 2);
            });
        $existsClause = "-exists-books.author_id_=_authors.id-id_=_2-authors.deleted_at_null";

        $this->assertEquals(
            $this->keyPrefix() . "-id_=_1" . $existsClause,
            $this->cacheKey($and)
        );
        $this->assertEquals(
            $this->keyPrefix() . "-id_=_1-or" . $existsClause,
            $this->cacheKey($or)
        );
    }

    public function testOrWhereHasReturnsItsOwnResultsAfterWhereHasWasCached()
    {
        $this->assertFirstQueryWasCached(
            (new Book)
                ->where("id", 1)
                ->whereHas("author", function ($query) {
                    $query->where("id", 2);
                }),
            "The whereHas-query",
            ["genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors"]
        );

        $results = (new Book)
            ->where("id", 1)
            ->orWhereHas("author", function ($query) {
                $query->where("id", 2);
            })
            ->get();
        $liveResults = (new UncachedBook)
            ->where("id", 1)
            ->orWhereHas("author", function ($query) {
                $query->where("id", 2);
            })
            ->get();

        $this->assertNotEmpty($results);
        $this->assertEquals($liveResults->pluck("id"), $results->pluck("id"));
    }

    public function testOrWhereInProducesDifferentCacheKeyThanWhereIn()
    {
        $and = (new Book)->where("id", 1)->whereIn("id", [1, 2]);
        $or = (new Book)->where("id", 1)->orWhereIn("id", [1, 2]);

        $this->assertEquals(
            $this->keyPrefix() . "-id_=_1-id_in_1_2",
            $this->cacheKey($and)
        );
        $this->assertEquals(
            $this->keyPrefix() . "-id_=_1-or-id_in_1_2",
            $this->cacheKey($or)
        );
    }

    public function testOrWhereInReturnsItsOwnResultsAfterWhereInWasCached()
    {
        $this->assertFirstQueryWasCached(
            (new Book)->where("id", 1)->whereIn("id", [1, 2]),
            "The whereIn-query"
        );

        $results = (new Book)->where("id", 1)->orWhereIn("id", [1, 2])->get();
        $liveResults = (new UncachedBook)->where("id", 1)->orWhereIn("id", [1, 2])->get();

        $this->assertEquals($liveResults->pluck("id"), $results->pluck("id"));
        $this->assertCount(2, $results);
    }

    public function testOrWhereNotInProducesDifferentCacheKeyThanWhereNotIn()
    {
        $and = (new Book)->where("id", 1)->whereNotIn("id", [1, 2]);
        $or = (new Book)->where("id", 1)->orWhereNotIn("id", [1, 2]);

        $this->assertEquals(
            $this->keyPrefix() . "-id_=_1-id_notin_1_2",
            $this->cacheKey($and)
        );
        $this->assertEquals(
            $this->keyPrefix() . "-id_=_1-or-id_notin_1_2",
            $this->cacheKey($or)
        );
    }

    public function testOrWhereNotInReturnsItsOwnResultsAfterWhereNotInWasCached()
    {
        $this->assertFirstQueryWasCached(
            (new Book)->where("id", 1)->whereNotIn("id", [1, 2]),
            "The whereNotIn-query"
        );

        $results = (new Book)->where("id", 1)->orWhereNotIn("id", [1, 2])->get();
        $liveResults = (new UncachedBook)->where("id", 1)->orWhereNotIn("id", [1, 2])->get();

        $this->assertNotEmpty($results);
        $this->assertEquals($liveResults->pluck("id"), $results->pluck("id"));
    }

    public function testOrWhereIntegerInRawProducesDifferentCacheKeyThanWhereIntegerInRaw()
    {
        $and = (new Book)->where("id", 1)->whereIntegerInRaw("id", [1, 2]);
        $or = (new Book)->where("id", 1)->orWhereIntegerInRaw("id", [1, 2]);

        $this->assertEquals(
            $this->keyPrefix() . "-id_=_1-id_inraw_1_2",
            $this->cacheKey($and)
        );
        $this->assertEquals(
            $this->keyPrefix() . "-id_=_1-or-id_inraw_1_2",
            $this->cacheKey($or)
        );
    }

    public function testOrWhereIntegerInRawReturnsItsOwnResultsAfterWhereIntegerInRawWasCached()
    {
        $this->assertFirstQueryWasCached(
            (new Book)->where("id", 1)->whereIntegerInRaw("id", [1, 2]),
            "The whereIntegerInRaw-query"
        );

        $results = (new Book)->where("id", 1)->orWhereIntegerInRaw("id", [1, 2])->get();
        $liveResults = (new UncachedBook)->where("id", 1)->orWhereIntegerInRaw("id", [1, 2])->get();

        $this->assertCount(2, $results);
        $this->assertEquals($liveResults->pluck("id"), $results->pluck("id"));
    }
}
