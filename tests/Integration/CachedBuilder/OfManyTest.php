<?php namespace GeneaLabs\LaravelModelCaching\Tests\Integration\CachedBuilder;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;

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
                $relation = $author->oldestBook();
                $relation->addEagerConstraints([$author]);

                return (new \GeneaLabs\LaravelModelCaching\CacheKey(
                    [],
                    $relation->getRelated(),
                    $relation->getQuery()->getQuery(),
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

        $this->assertStringNotContainsString("beforeQuery", $key);
    }
}
