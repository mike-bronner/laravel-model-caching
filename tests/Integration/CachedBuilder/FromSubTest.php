<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching\Tests\Integration\CachedBuilder;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;
use ReflectionMethod;

class FromSubTest extends IntegrationTestCase
{
    private function cacheKey($query) : string
    {
        return (new ReflectionMethod($query, "makeCacheKey"))
            ->invoke($query);
    }

    public function testFromSubQueryIsCached()
    {
        $results = (new Author)
            ->fromSub(
                fn ($query) => $query->from("authors")->where("id", ">", 1),
                "authors",
            )
            ->get();
        $liveResults = (new UncachedAuthor)
            ->fromSub(
                fn ($query) => $query->from("authors")->where("id", ">", 1),
                "authors",
            )
            ->get();

        $this->assertNotEmpty($results);
        $this->assertEquals($liveResults->pluck("id"), $results->pluck("id"));
    }

    public function testTwoDifferentFromSubQueriesDoNotShareAKey()
    {
        $first = (new Author)
            ->fromSub(
                fn ($query) => $query->from("authors")->where("id", ">", 1),
                "authors",
            );
        $second = (new Author)
            ->fromSub(
                fn ($query) => $query->from("authors")->where("id", ">", 2),
                "authors",
            );

        $this->assertNotSame($this->cacheKey($first), $this->cacheKey($second));

        $liveResults = (new UncachedAuthor)
            ->fromSub(
                fn ($query) => $query->from("authors")->where("id", ">", 2),
                "authors",
            )
            ->get();

        $first->get();

        $this->assertEquals($liveResults->pluck("id"), $second->get()->pluck("id"));
    }
}
