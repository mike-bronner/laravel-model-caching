<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;

// whereBetweenColumns() carries no "operator", so the cache key builder used to
// read a key that is not there and raise "Undefined array key". Under Laravel's
// default error handler that converts to an ErrorException, so the query threw
// instead of returning rows.

test('where between columns produces a cache key without raising a warning', function () {
    $query = (new Book)->whereBetweenColumns("published_at", ["created_at", "updated_at"]);

    expect(cacheKey($query))->toEqual(
        keyPrefix() . "-published_at_betweencolumns_created_at_updated_at",
    );
});

test('where not between columns produces a different cache key', function () {
    $between = (new Book)->whereBetweenColumns("published_at", ["created_at", "updated_at"]);
    $notBetween = (new Book)->whereNotBetweenColumns("published_at", ["created_at", "updated_at"]);

    expect(cacheKey($between))->toEqual(
        keyPrefix() . "-published_at_betweencolumns_created_at_updated_at",
    );
    expect(cacheKey($notBetween))->toEqual(
        keyPrefix() . "-published_at_not_betweencolumns_created_at_updated_at",
    );
});

test('where between columns returns rows rather than throwing', function () {
    $results = (new Book)
        ->whereBetweenColumns("published_at", ["created_at", "updated_at"])
        ->get();
    $liveResults = (new UncachedBook)
        ->whereBetweenColumns("published_at", ["created_at", "updated_at"])
        ->get();

    expect($results->pluck("id"))->toEqual($liveResults->pluck("id"));
});

test('where not between columns returns its own results after where between columns was cached', function () {
    $betweenQuery = (new Book)
        ->whereBetweenColumns("published_at", ["created_at", "updated_at"]);
    $betweenKey = sha1(cacheKey($betweenQuery));
    $betweenResults = $betweenQuery->get();
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
    ];

    $cached = $this->cache()
        ->tags($tags)
        ->get($betweenKey);

    expect($cached)->not->toBeNull(
        "The first query must populate the cache, or the second query has nothing to collide with",
    );
    expect($cached["value"]->pluck("id"))->toEqual($betweenResults->pluck("id"));

    $results = (new Book)
        ->whereNotBetweenColumns("published_at", ["created_at", "updated_at"])
        ->get();
    $liveResults = (new UncachedBook)
        ->whereNotBetweenColumns("published_at", ["created_at", "updated_at"])
        ->get();

    expect($results)->not->toBeEmpty();
    expect($results->pluck("id"))->toEqual($liveResults->pluck("id"));
});
