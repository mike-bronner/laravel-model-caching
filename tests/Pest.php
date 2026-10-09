<?php

use GeneaLabs\LaravelModelCaching\CachedBelongsToMany;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\ThrowingCacheStore;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;
use Illuminate\Cache\CacheManager;
use Illuminate\Cache\Repository;

pest()->extend(IntegrationTestCase::class)->in('Feature', 'Integration');

// Test Impact Analysis exists from Pest 5 on. CI cells that resolve Pest 4
// load this file too, so the call is guarded rather than made unconditionally.
// locally() turns TIA on for a plain local run. A run that passes --testsuite,
// as composer test and CI do, is a partial run to TIA and stays a full run.
if (method_exists(pest(), 'tia')) {
    pest()->tia()->locally()->baselined();
}

function cacheKey($query) : string
{
    return (new ReflectionMethod($query, "makeCacheKey"))
        ->invoke($query);
}

function keyPrefix() : string
{
    return "genealabs:laravel-model-caching:testing:" . test()->testingSqlitePath . "testing.sqlite"
        . ":books:genealabslaravelmodelcachingtestsfixturesbook";
}

function bookTags() : array
{
    return [
        "genealabs:laravel-model-caching:testing:" . test()->testingSqlitePath . "testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
        "genealabs:laravel-model-caching:testing:" . test()->testingSqlitePath . "testing.sqlite:books",
    ];
}

function authorTags() : array
{
    return [
        "genealabs:laravel-model-caching:testing:" . test()->testingSqlitePath . "testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:" . test()->testingSqlitePath . "testing.sqlite:authors",
    ];
}

function assertCachedUnderTags($query, array $tags) : void
{
    $key = sha1(cacheKey($query));
    $query->get();

    expect(test()->cache()->tags($tags)->get($key))
        ->not->toBeNull("The query was not cached under the expected tags.");
}

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

function getCacheTagsAndKey(CachedBelongsToMany $relation): array
{
    $reflTags = new ReflectionMethod($relation, 'makeCacheTags');
    $tags = $reflTags->invoke($relation);

    $reflKey = new ReflectionMethod($relation, 'makeCacheKey');
    $rawKey = $reflKey->invoke($relation);

    return [$tags, sha1($rawKey)];
}

function breakCacheConnection(string $exceptionClass = RedisException::class): void
{
    $throwingStore = new ThrowingCacheStore($exceptionClass);
    $throwingRepo = new Repository($throwingStore);

    app()->extend('cache', function ($cache) use ($throwingRepo) {
        return new class(app(), $throwingRepo) extends CacheManager
        {
            public function __construct($app, private Repository $throwingRepo)
            {
                parent::__construct($app);
            }

            public function store($name = null)
            {
                return $this->throwingRepo;
            }

            public function driver($driver = null)
            {
                return $this->throwingRepo;
            }
        };
    });
}
