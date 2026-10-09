<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;

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

test('where not produces different cache key than where', function () {
    $where = (new Book)->where("id", 1);
    $whereNot = (new Book)->whereNot("id", 1);

    expect(cacheKey($where))->toEqual(keyPrefix() . "-id_=_1");
    expect(cacheKey($whereNot))->toEqual(keyPrefix() . "-and_not-id_=_1");
});

test('where not returns its own results after where was cached', function () {
    $whereQuery = (new Book)->where("id", 1);
    $whereKey = sha1(cacheKey($whereQuery));
    $whereResults = $whereQuery->get();

    $cachedWhereResults = $this->cache()
        ->tags(bookTags())
        ->get($whereKey);

    expect($cachedWhereResults)->not->toBeNull(
        "The first query must populate the cache, or the second query has nothing to collide with",
    );
    expect($cachedWhereResults["value"]->pluck("id"))->toEqual($whereResults->pluck("id"));

    $results = (new Book)->whereNot("id", 1)->get();
    $liveResults = (new UncachedBook)->whereNot("id", 1)->get();

    expect($results->pluck("id"))->toEqual($liveResults->pluck("id"));
    expect($results->pluck("id")->toArray())->not->toContain(1);
});

test('where not between produces different cache key than where between', function () {
    $between = (new Book)->whereBetween("id", [1, 2]);
    $notBetween = (new Book)->whereNotBetween("id", [1, 2]);

    expect(cacheKey($between))->toEqual(keyPrefix() . "-id_between_1_2");
    expect(cacheKey($notBetween))->toEqual(keyPrefix() . "-id_not_between_1_2");
});

test('where not between returns its own results after where between was cached', function () {
    $betweenQuery = (new Book)->whereBetween("id", [1, 2]);
    $betweenKey = sha1(cacheKey($betweenQuery));
    $betweenResults = $betweenQuery->get();

    $cachedBetweenResults = $this->cache()
        ->tags(bookTags())
        ->get($betweenKey);

    expect($cachedBetweenResults)->not->toBeNull(
        "The first query must populate the cache, or the second query has nothing to collide with",
    );
    expect($cachedBetweenResults["value"]->pluck("id"))->toEqual($betweenResults->pluck("id"));

    $results = (new Book)->whereNotBetween("id", [1, 2])->get();
    $liveResults = (new UncachedBook)->whereNotBetween("id", [1, 2])->get();

    expect($results->pluck("id"))->toEqual($liveResults->pluck("id"));
    expect(array_intersect([1, 2], $results->pluck("id")->toArray()))->toBeEmpty();
});

// whereJsonContains() gets no result-level twin: SQLite cannot compile a
// JSON "contains" predicate at all, and the suite's PostgreSQL-backed JSON
// tests are skipped unless that server is running. The key is built without
// touching the database, and the key is where the defect lived.
test('where json doesnt contain produces different cache key than where json contains', function () {
    $contains = (new Author)->whereJsonContains("finances->total", 5000);
    $doesntContain = (new Author)->whereJsonDoesntContain("finances->total", 5000);

    expect(cacheKey($contains))->toEqual(
        authorKeyPrefix() . "-finances->total_jsoncontains_5000-authors.deleted_at_null",
    );
    expect(cacheKey($doesntContain))->toEqual(
        authorKeyPrefix() . "-finances->total_not_jsoncontains_5000-authors.deleted_at_null",
    );
});

test('where json doesnt contain key produces different cache key than where json contains key', function () {
    $containsKey = (new Author)->whereJsonContainsKey("finances->total");
    $doesntContainKey = (new Author)->whereJsonDoesntContainKey("finances->total");

    expect(cacheKey($containsKey))->toEqual(
        authorKeyPrefix() . "-finances->total_jsoncontainskey_-authors.deleted_at_null",
    );
    expect(cacheKey($doesntContainKey))->toEqual(
        authorKeyPrefix() . "-finances->total_not_jsoncontainskey_-authors.deleted_at_null",
    );
});

test('where json contains key returns its own results after where json doesnt contain key was cached', function () {
    $doesntContainKeyQuery = (new Author)->whereJsonDoesntContainKey("finances->total");
    $doesntContainKeyKey = sha1(cacheKey($doesntContainKeyQuery));
    $doesntContainKeyResults = $doesntContainKeyQuery->get();

    $cachedResults = $this->cache()
        ->tags(authorTags())
        ->get($doesntContainKeyKey);

    expect($cachedResults)->not->toBeNull(
        "The first query must populate the cache, or the second query has nothing to collide with",
    );
    expect($cachedResults["value"]->pluck("id"))->toEqual($doesntContainKeyResults->pluck("id"));

    $results = (new Author)->whereJsonContainsKey("finances->total")->get();
    $liveResults = (new UncachedAuthor)->whereJsonContainsKey("finances->total")->get();

    expect($results)->not->toBeEmpty();
    expect($doesntContainKeyResults)->toBeEmpty();
    expect($results->pluck("id"))->toEqual($liveResults->pluck("id"));
});

// The JSON families need a real JSON column, which only the authors table
// has. Author's SoftDeletes global scope is harmless here — it adds one
// fixed clause to the key, and unlike the "or" cases it never triggers
// addNewWheresWithinGroup(), because a "not" flag is not a boolean.
function authorKeyPrefix(): string
{
    $testingSqlitePath = test()->testingSqlitePath;

    return "genealabs:laravel-model-caching:testing:{$testingSqlitePath}testing.sqlite"
        . ":authors:genealabslaravelmodelcachingtestsfixturesauthor";
}
