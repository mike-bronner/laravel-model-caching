<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;

test('cache can be disabled on model', function () {
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor");
    $tags = ["genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor"];
    $authors = (new Author)
        ->disableCache()
        ->get();

    $cachedResults = $this->cache()
        ->tags($tags)
        ->get($key);
    $liveResults = (new UncachedAuthor)
        ->get();

    expect($liveResults->diffAssoc($authors))->toBeEmpty();
    expect($cachedResults)->toBeNull();
});

test('cache can be disabled on query', function () {
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-testing:{$this->testingSqlitePath}testing.sqlite:books");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];
    $authors = (new Author)
        ->with('books')
        ->disableCache()
        ->get()
        ->keyBy("id");

    $cachedResults = $this->cache()
        ->tags($tags)
        ->get($key);
    $liveResults = (new UncachedAuthor)
        ->with('books')
        ->get()
        ->keyBy("id");

    expect($cachedResults)->toBeNull();
    expect($liveResults->diffKeys($authors))->toBeEmpty();
});
