<?php namespace GeneaLabs\LaravelModelCaching\Tests\Integration\CachedBuilder;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;
use ReflectionMethod;

// A subquery carrying no where clause used to make getNestedClauses() call
// getWhereClauses([]), which getWheres() read as "no argument given" and
// answered with the outer query's clauses. Those still hold the clause that
// asked, so the walk had no floor and the process died on SIGSEGV.
//
// A segfault is not an exception, so these tests cannot assert on one. They
// assert the query returns its rows instead: without the fix the PHP process
// never reaches the assertion and PHPUnit reports the crash.
class NestedSubqueryRecursionTest extends IntegrationTestCase
{
    private function cacheKey($query) : string
    {
        return (new ReflectionMethod($query, "makeCacheKey"))
            ->invoke($query);
    }

    public function testWhereExistsWithoutInnerWhereClausesBuildsAKey()
    {
        $books = (new Book)
            ->whereExists(fn ($query) => $query->select("id")->from("authors"))
            ->get();
        $liveResults = (new UncachedBook)
            ->whereExists(fn ($query) => $query->select("id")->from("authors"))
            ->get();

        $this->assertEquals($liveResults->pluck("id"), $books->pluck("id"));
        $this->assertNotEmpty($books);
    }

    public function testWhereNotExistsWithoutInnerWhereClausesBuildsAKey()
    {
        $books = (new Book)
            ->whereNotExists(fn ($query) => $query->select("id")->from("authors"))
            ->get();
        $liveResults = (new UncachedBook)
            ->whereNotExists(fn ($query) => $query->select("id")->from("authors"))
            ->get();

        $this->assertEquals($liveResults->pluck("id"), $books->pluck("id"));
    }

    // The empty subquery has to key as empty rather than as a copy of the
    // outer clauses. Asserting only that the query completes would stay green
    // if the recursion were replaced by a depth cap, which would still write
    // the outer clauses into the nested segment.
    public function testEmptyNestedSubqueryContributesNoClausesToTheKey()
    {
        $key = $this->cacheKey((new Book)
            ->where("title", "a")
            ->whereExists(fn ($query) => $query->select("id")->from("authors")));

        $this->assertStringEndsWith("-title_=_a-exists", $key);
    }

    public function testConstrainedNestedSubqueryStillContributesItsOwnClauses()
    {
        $key = $this->cacheKey((new Book)
            ->where("title", "a")
            ->whereExists(fn ($query) => $query->select("id")->from("authors")->where("id", 1)));

        $this->assertStringEndsWith("-title_=_a-exists-id_=_1", $key);
    }

    public function testWhereExistsStillReturnsItsOwnRowsAfterWhereNotExistsWasCached()
    {
        (new Book)
            ->whereNotExists(fn ($query) => $query->select("id")->from("authors"))
            ->get();

        $books = (new Book)
            ->whereExists(fn ($query) => $query->select("id")->from("authors"))
            ->get();
        $liveResults = (new UncachedBook)
            ->whereExists(fn ($query) => $query->select("id")->from("authors"))
            ->get();

        $this->assertEquals($liveResults->pluck("id"), $books->pluck("id"));
    }
}
