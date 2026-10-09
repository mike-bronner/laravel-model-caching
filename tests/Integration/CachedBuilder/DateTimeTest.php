<?php

use GeneaLabs\LaravelModelCaching\CacheKey;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;
use Illuminate\Support\Carbon;

test('get values from where formats every date time type identically', function () {
    $expected = "2019-03-17-13-05-42";
    $whereFor = fn ($value) => [
        "type" => "Basic",
        "column" => "published_at",
        "operator" => ">",
        "value" => $value,
        "boolean" => "and",
    ];

    expect(valuesFromWhere($whereFor(new DateTime("2019-03-17 13:05:42"))))->toBe($expected);
    expect(valuesFromWhere($whereFor(new DateTimeImmutable("2019-03-17 13:05:42"))))->toBe(
        $expected,
    );
    expect(valuesFromWhere($whereFor((new Carbon)->parse("2019-03-17 13:05:42"))))->toBe($expected);
});

test('carbon binding is keyed by our format not its own cast', function () {
    $key = rawCacheKeyFor((new Carbon)->parse("2019-03-17 13:05:42"));
    expect($key)->toContain("published_at_>_2019%2D03%2D17%2D13%2D05%2D42");
});

test('carbon key segment ignores the global to string format', function () {
    $dateTime = (new Carbon)->parse("2019-03-17 13:05:42");
    $before = rawCacheKeyFor($dateTime);

    Carbon::setToStringFormat("D, d M Y H:i:s");

    try {
        expect(rawCacheKeyFor($dateTime))->toBe($before);
    } finally {
        Carbon::resetToStringFormat();
    }
});

test('where clause works with carbon date', function () {
    $dateTime = now()->subYears(10);
    $encodedDateTime = str_replace("-", "%2D", $dateTime->format("Y-m-d-H-i-s"));
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books:genealabslaravelmodelcachingtestsfixturesbook-published_at_>_{$encodedDateTime}");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
    ];

    $results = (new Book)
        ->where("published_at", ">", $dateTime)
        ->get();
    $cachedResults = $this->cache()
        ->tags($tags)
        ->get($key)['value'];
    $liveResults = (new UncachedBook)
        ->where("published_at", ">", $dateTime)
        ->get();

    expect($results->pluck("id"))->toEqual($liveResults->pluck("id"));
    expect($cachedResults->pluck("id"))->toEqual($liveResults->pluck("id"));
    expect($results)->not->toBeEmpty();
    expect($cachedResults)->not->toBeEmpty();
    expect($liveResults)->not->toBeEmpty();
    // A predicate matching every row makes the comparison above vacuous,
    // which is what querying the absent "publish_at" column used to do.
    expect($liveResults->count())->toBeLessThan((new UncachedBook)->count());
});

test('where clause works with date time immutable object', function () {
    $dateTime = (new DateTimeImmutable('@' . time()))
        ->sub(new DateInterval("P10Y"));
    $dateTimeString = str_replace("-", "%2D", $dateTime->format("Y-m-d-H-i-s"));
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books:genealabslaravelmodelcachingtestsfixturesbook-published_at_>_{$dateTimeString}");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
    ];

    $results = (new Book)
        ->where("published_at", ">", $dateTime)
        ->get();
    $cachedResults = $this->cache()
        ->tags($tags)
        ->get($key)['value'];
    $liveResults = (new UncachedBook)
        ->where("published_at", ">", $dateTime)
        ->get();

    expect($results->pluck("id"))->toEqual($liveResults->pluck("id"));
    expect($cachedResults->pluck("id"))->toEqual($liveResults->pluck("id"));
    expect($results)->not->toBeEmpty();
    expect($cachedResults)->not->toBeEmpty();
    expect($liveResults)->not->toBeEmpty();
    // A predicate matching every row makes the comparison above vacuous,
    // which is what querying the absent "publish_at" column used to do.
    expect($liveResults->count())->toBeLessThan((new UncachedBook)->count());
});

test('where clause works with date time object', function () {
    $dateTime = (new DateTime('@' . time()))
        ->sub(new DateInterval("P10Y"));
    $dateTimeString = str_replace("-", "%2D", $dateTime->format("Y-m-d-H-i-s"));
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books:genealabslaravelmodelcachingtestsfixturesbook-published_at_>_{$dateTimeString}");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
    ];

    $results = (new Book)
        ->where("published_at", ">", $dateTime)
        ->get();
    $cachedResults = $this->cache()
        ->tags($tags)
        ->get($key)['value'];
    $liveResults = (new UncachedBook)
        ->where("published_at", ">", $dateTime)
        ->get();

    expect($results->pluck("id"))->toEqual($liveResults->pluck("id"));
    expect($cachedResults->pluck("id"))->toEqual($liveResults->pluck("id"));
    expect($results)->not->toBeEmpty();
    expect($cachedResults)->not->toBeEmpty();
    expect($liveResults)->not->toBeEmpty();
    // A predicate matching every row makes the comparison above vacuous,
    // which is what querying the absent "publish_at" column used to do.
    expect($liveResults->count())->toBeLessThan((new UncachedBook)->count());
});

function valuesFromWhere(array $where): string
{
    $query = (new Book)->newQuery();
    $cacheKey = new CacheKey([], new Book, $query->getQuery(), "", [], false);

    return (new ReflectionMethod($cacheKey, "getValuesFromWhere"))
        ->invoke($cacheKey, $where);
}

function rawCacheKeyFor(object $value): string
{
    $query = (new Book)
        ->newQuery()
        ->where("published_at", ">", $value);

    return (new CacheKey([], new Book, $query->getQuery(), "", [], false))
        ->make();
}
