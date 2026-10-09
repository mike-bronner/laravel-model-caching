<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use Illuminate\Database\Eloquent\Collection;

test('disabling all query', function () {
    $allAuthors = (new Author)
        ->disableCache()
        ->all();
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];
    $cachedAuthors = $this
        ->cache()
        ->tags($tags)
        ->get($key)["value"]
        ?? null;

    expect($allAuthors)->toBeInstanceOf(Collection::class);
    expect($cachedAuthors)->toBeNull();
});

test('using truncate invalidates cache', function () {
    (new Author)->get();
    Author::truncate();

    expect((new Author)->get()->isEmpty())->toBeTrue();
});
