<?php namespace GeneaLabs\LaravelModelCaching\Tests\Integration\CachedBuilder;

use GeneaLabs\LaravelModelCaching\CacheKey;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\Relation;

class OfManyTest extends IntegrationTestCase
{
    public function testPaginatedOfManyEagerLoadReturnsCorrectRelationOnLaterPages()
    {
        $pageOne = (new Author)
            ->with("oldestBook")
            ->orderBy("id")
            ->paginate(perPage: 1, page: 1);
        $pageTwo = (new Author)
            ->with("oldestBook")
            ->orderBy("id")
            ->paginate(perPage: 1, page: 2);

        $authorOne = $pageOne->first();
        $authorTwo = $pageTwo->first();

        $this->assertNotNull($authorOne->oldestBook);
        $this->assertNotNull($authorTwo->oldestBook);
        $this->assertEquals(
            (new UncachedBook)->where("author_id", $authorTwo->id)->min("id"),
            $authorTwo->oldestBook->id,
        );
    }

    public function testPaginatedOldestOfManyEagerLoadReturnsCorrectRelationOnLaterPages()
    {
        (new Author)
            ->with("firstBook")
            ->orderBy("id")
            ->paginate(perPage: 1, page: 1);
        $pageTwo = (new Author)
            ->with("firstBook")
            ->orderBy("id")
            ->paginate(perPage: 1, page: 2);

        $authorTwo = $pageTwo->first();

        $this->assertNotNull($authorTwo->firstBook);
        $this->assertEquals(
            (new UncachedBook)->where("author_id", $authorTwo->id)->min("id"),
            $authorTwo->firstBook->id,
        );
    }

    public function testPaginatedLatestOfManyEagerLoadReturnsCorrectRelationOnLaterPages()
    {
        (new Author)
            ->with("newestBook")
            ->orderBy("id")
            ->paginate(perPage: 1, page: 1);
        $pageTwo = (new Author)
            ->with("newestBook")
            ->orderBy("id")
            ->paginate(perPage: 1, page: 2);

        $authorTwo = $pageTwo->first();

        $this->assertNotNull($authorTwo->newestBook);
        $this->assertEquals(
            (new UncachedBook)->where("author_id", $authorTwo->id)->max("id"),
            $authorTwo->newestBook->id,
        );
    }

    public function testOfManyQueriesWithDifferentBindingsProduceDifferentCacheKeys()
    {
        $authors = (new Author)->orderBy("id")->take(2)->get();

        $keys = $authors
            ->map(function (Author $author) {
                // Build the relation the way eager loading does, inside
                // `Relation::noConstraints()`. Calling `$author->oldestBook()`
                // directly runs `HasOneOrMany::addConstraints()` while
                // `isOneOfMany` is still false, which bakes a plain
                // `where author_id = ?` onto the OUTER query — that ordinary
                // clause alone would differentiate the two keys through
                // `getWhereClauses()`, so the test would pass even with the
                // deferred slug gutted. Suppressing it leaves the deferred
                // `beforeQuery` join bindings as the only differentiator.
                $relation = Relation::noConstraints(
                    fn () => (new Author)->oldestBook(),
                );
                $relation->addEagerConstraints([$author]);
                $query = $relation->getQuery()->getQuery();

                $this->assertSame([], $query->wheres ?? []);

                return (new CacheKey(
                    [],
                    $relation->getRelated(),
                    $query,
                    "",
                    [],
                    false,
                ))
                    ->make(["*"]);
            });

        $this->assertStringContainsString("-beforeQuery_", $keys->first());
        $this->assertStringContainsString("-beforeQuery_", $keys->last());
        $this->assertNotEquals($keys->first(), $keys->last());
    }

    public function testCompositeOfManyEagerLoadMatchesUncachedResult()
    {
        $author = Author::factory()->create();
        Book::factory()->create([
            "author_id" => $author->id,
            "price" => 10.00,
            "published_at" => "2023-01-01 00:00:00",
        ]);
        Book::factory()->create([
            "author_id" => $author->id,
            "price" => 99.00,
            "published_at" => "2021-01-01 00:00:00",
        ]);
        // Ties the highest price, so the second aggregate (published_at)
        // decides — the composite path, not the single-column one.
        $expectedBook = Book::factory()->create([
            "author_id" => $author->id,
            "price" => 99.00,
            "published_at" => "2022-01-01 00:00:00",
        ]);

        $cachedAuthor = (new Author)
            ->with("latestBookByPriceThenDate")
            ->find($author->id);
        $uncachedBook = (new UncachedBook)
            ->where("author_id", $author->id)
            ->orderByDesc("price")
            ->orderByDesc("published_at")
            ->first();

        $this->assertNotNull($uncachedBook);
        $this->assertNotNull($cachedAuthor->latestBookByPriceThenDate);
        $this->assertEquals(
            $expectedBook->id,
            $cachedAuthor->latestBookByPriceThenDate->id,
        );
        $this->assertEquals(
            $uncachedBook->id,
            $cachedAuthor->latestBookByPriceThenDate->id,
        );
    }

    public function testCompositeOfManyCacheKeyGenerationLeavesTheExecutedQueryUnchanged()
    {
        $author = (new Author)->orderBy("id")->first();

        // Control: an identical relation that never has a cache key generated
        // from it. Its compiled SQL is what the subject must still produce.
        $controlQuery = $this
            ->makeCompositeOfManyRelation($author)
            ->getQuery()
            ->getQuery();

        $subject = $this->makeCompositeOfManyRelation($author);
        $subjectQuery = $subject->getQuery()->getQuery();

        // Key generation runs the deferred callbacks speculatively on a clone.
        // The composite relation's callbacks mutate a nested subquery that the
        // clone SHARES with the original, so do it twice: the second pass must
        // observe the already-mutated subquery and still produce the same key.
        $keys = collect([1, 2])
            ->map(fn () => (new CacheKey(
                [],
                $subject->getRelated(),
                $subjectQuery,
                "",
                [],
                false,
            ))
                ->make(["*"]));

        $this->assertStringContainsString("-beforeQuery_", $keys->first());
        $this->assertSame($keys->first(), $keys->last());

        $controlSql = $controlQuery->toSql();

        // Guards the comparison below against passing on two empty or
        // join-less strings: the joined subquery must really be there, and
        // both aggregate columns must take part in it. Matches the join alias
        // and the column names rather than aggregate syntax, so it survives
        // grammar differences across the supported Laravel versions.
        $this->assertStringContainsString("inner join", $controlSql);
        $this->assertStringContainsString(
            '"latestBookByPriceThenDate"',
            $controlSql,
        );
        $this->assertStringContainsString("price", $controlSql);
        $this->assertStringContainsString("published_at", $controlSql);

        $this->assertSame($controlSql, $subjectQuery->toSql());
        $this->assertSame(
            $controlQuery->getBindings(),
            $subjectQuery->getBindings(),
        );
    }

    public function testDeferredQueriesWithDifferentBinaryBindingsProduceDifferentCacheKeys()
    {
        $keys = collect([
            hex2bin("ffd8ffe000104a4649460001abcdef01"),
            hex2bin("ffd8ffe000104a4649460001abcdef02"),
        ])
            ->map(function (string $binaryId) {
                $query = (new Author)->newQueryWithoutScopes()->getQuery();
                $query->beforeQuery(function ($query) use ($binaryId) {
                    $query->where("id", $binaryId);
                });

                return (new \GeneaLabs\LaravelModelCaching\CacheKey(
                    [],
                    new Author,
                    $query,
                    "",
                    [],
                    false,
                ))
                    ->make(["*"]);
            });

        $this->assertNotEquals($keys->first(), $keys->last());
    }

    public function testQueriesWithoutDeferredCallbacksKeepTheirCacheKeyFormat()
    {
        $key = (new \GeneaLabs\LaravelModelCaching\CacheKey(
            [],
            new Author,
            (new Author)->newQueryWithoutScopes()->getQuery(),
            "",
            [],
            false,
        ))
            ->make(["*"]);

        $this->assertEquals(
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor",
            $key,
        );
    }

    // Builds the composite `ofMany()` relation through the eager-load path, so
    // no ordinary `where` lands on the outer query and the deferred callbacks
    // stay the only source of the join.
    private function makeCompositeOfManyRelation(Author $author) : HasOne
    {
        $relation = Relation::noConstraints(
            fn () => (new Author)->latestBookByPriceThenDate(),
        );
        $relation->addEagerConstraints([$author]);

        return $relation;
    }
}
