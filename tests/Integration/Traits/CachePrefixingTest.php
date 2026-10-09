<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;

test('database keying enabled', function () {
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null-first");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $author = (new Author)
        ->first();
    $cachedResults = $this->cache()
        ->tags($tags)
        ->get($key)['value'];
    $liveResults = (new UncachedAuthor)
        ->first();

    expect($author->pluck("id"))->toEqual($liveResults->pluck("id"));
    expect($cachedResults->pluck("id"))->toEqual($liveResults->pluck("id"));
    expect($author)->not->toBeEmpty();
    expect($cachedResults)->not->toBeEmpty();
    expect($liveResults)->not->toBeEmpty();
});

test('database keying disabled', function () {
    config(["laravel-model-caching.use-database-keying" => false]);
    $key = sha1("genealabs:laravel-model-caching:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null-first");
    $tags = [
        "genealabs:laravel-model-caching:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:authors",
    ];

    $author = (new Author)
        ->first();
    $cachedResults = $this->cache()
        ->tags($tags)
        ->get($key)['value'];
    $liveResults = (new UncachedAuthor)
        ->first();

    expect($author->pluck("id"))->toEqual($liveResults->pluck("id"));
    expect($cachedResults->pluck("id"))->toEqual($liveResults->pluck("id"));
    expect($author)->not->toBeEmpty();
    expect($cachedResults)->not->toBeEmpty();
    expect($liveResults)->not->toBeEmpty();
});
