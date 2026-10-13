<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;

test('get model results creates cache', function () {
    $authors = (new Author)
        ->limit(5)
        ->get();
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null-limit_5");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $cachedResults = $this
        ->cache()
        ->tags($tags)
        ->get($key)['value'];
    $liveResults = (new UncachedAuthor)
        ->limit(5)
        ->get();

    expect($cachedResults)->toEqual($authors);
    expect($liveResults->diffKeys($cachedResults))->toBeEmpty();
});
