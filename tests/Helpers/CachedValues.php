<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;

function assertFirstQueryWasCached($query, string $description, array $extraTags = []) : void
{
    $key = sha1(cacheKey($query));
    $results = $query->get();
    $cached = test()->cache()
        ->tags([...bookTags(), ...$extraTags])
        ->get($key);

    expect($cached)->not->toBeNull(
        "{$description} must populate the cache, or the second query has nothing to collide with",
    );
    expect($cached["value"]->pluck("id"))->toEqual($results->pluck("id"));
}

function populateBookCache(): string
{
    (new Book)->all();

    $key = sha1(
        "genealabs:laravel-model-caching:testing:" . test()->testingSqlitePath . "testing.sqlite:books:genealabslaravelmodelcachingtestsfixturesbook"
    );

    expect(test()->cache()->tags(bookTags())->get($key))->not->toBeNull();

    return $key;
}

function populateAuthorCache(): string
{
    $testingSqlitePath = test()->testingSqlitePath;

    (new Author)->all();

    $key = sha1(
        "genealabs:laravel-model-caching:testing:{$testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null"
    );

    expect(test()->cache()->tags(authorTags())->get($key))->not->toBeNull();

    return $key;
}

function cachedValueFor(string $key)
{
    return test()->cache()
        ->tags(postgresAuthorTags())
        ->get($key);
}

function booksTagEntrySets() : array
{
    $token = (int) env("TEST_TOKEN", 1);

    return test()->scanRedisKeys(
        app("redis")->connection("model-cache"),
        "lmc-test-{$token}:tag:genealabs:laravel-model-caching:*:books:entries",
    );
}

function modelCacheKeys() : array
{
    $token = (int) env('TEST_TOKEN', 1);
    $connection = app('redis')->connection('model-cache');

    return test()->scanRedisKeys($connection, "lmc-test-{$token}:*");
}

function packageKeys(): array
{
    $token = (int) env("TEST_TOKEN", 1);

    return test()->scanRedisKeys(app("redis")->connection("model-cache"), "lmc-test-{$token}:*");
}
