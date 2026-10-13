<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;

test('with in using collection query no operator', function () {
    $length = 2;
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-finances->tags_jsonlength_=_$length-authors.deleted_at_null");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $authors = (new Author)->whereJsonLength("finances->tags", $length)->get();
    $liveResults = (new UncachedAuthor)->whereJsonLength("finances->tags", $length)->get();

    $cachedResults = $this
        ->cache()
        ->tags($tags)
        ->get($key)['value'];

    expect($liveResults)->toHaveCount(10);
    expect($cachedResults)->toHaveCount(10);
    expect($authors->pluck("id"))->toEqual($liveResults->pluck("id"));
    expect($cachedResults->pluck("id"))->toEqual($liveResults->pluck("id"));
});

test('with in using collection query with operator', function () {
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-finances->tags_jsonlength_>_1-authors.deleted_at_null");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $authors = (new Author)->whereJsonLength("finances->tags", '>', 1)->get();
    $liveResults = (new UncachedAuthor)->whereJsonLength("finances->tags", '>', 1)->get();

    $cachedResults = $this
        ->cache()
        ->tags($tags)
        ->get($key)['value'];

    expect($liveResults)->toHaveCount(10);
    expect($cachedResults)->toHaveCount(10);
    expect($authors->pluck("id"))->toEqual($liveResults->pluck("id"));
    expect($cachedResults->pluck("id"))->toEqual($liveResults->pluck("id"));
});
