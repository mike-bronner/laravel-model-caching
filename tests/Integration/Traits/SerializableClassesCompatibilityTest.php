<?php

use GeneaLabs\LaravelModelCaching\Cache\ModelCacheRepository;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;
use Illuminate\Database\Eloquent\Collection;

test('cacheable trait works when serializable classes is true', function () {
    config(["cache.serializable_classes" => true]);

    $cachedAuthors = Author::all();
    $liveAuthors = UncachedAuthor::all();

    expect($cachedAuthors)->toBeInstanceOf(Collection::class);
    expect($cachedAuthors)->toHaveCount($liveAuthors->count());
    expect($cachedAuthors->pluck(value: "id")->toArray())->toEqual(
        $liveAuthors->pluck(value: "id")->toArray(),
    );
});

test('models are cached and retrieved when serializable classes is false', function () {
    config(["cache.serializable_classes" => false]);

    $cachedAuthors = Author::all();
    $liveAuthors = UncachedAuthor::all();

    expect($cachedAuthors)->toBeInstanceOf(Collection::class);
    expect($cachedAuthors)->toHaveCount($liveAuthors->count());
});

test('raw stored value is serialized string not php object', function () {
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    Author::all();

    $rawValue = app("cache")
        ->store(config("laravel-model-caching.store"))
        ->tags($tags)
        ->get($key);

    expect($rawValue)->toBeString();
    expect($rawValue)->toStartWith(ModelCacheRepository::SERIALIZED_VALUE_PREFIX);
});

test('cached models match live results under serializable classes restriction', function () {
    config(["cache.serializable_classes" => true]);

    $firstCall = Author::all();
    $secondCall = Author::all();
    $liveAuthors = UncachedAuthor::all();

    expect($firstCall->pluck(value: "id")->toArray())->toEqual(
        $liveAuthors->pluck(value: "id")->toArray(),
    );
    expect($secondCall->pluck(value: "id")->toArray())->toEqual(
        $firstCall->pluck(value: "id")->toArray(),
    );
});
