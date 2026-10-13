<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;

test('find or fail caches models', function () {
    $author = (new Author)
        ->findOrFail(1);

    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null-find_1");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $cachedResults = $this->cache()
        ->tags($tags)
        ->get($key)['value'];
    $liveResults = (new UncachedAuthor)
        ->findOrFail(1);

    expect($author->toArray())->toEqual($cachedResults->toArray());
    expect($author->toArray())->toEqual($liveResults->toArray());
});

test('find or fail with array returns results', function () {
    $author = (new Author)->findOrFail([1, 2]);
    $uncachedAuthor = (new UncachedAuthor)->findOrFail([1, 2]);

    expect($author->count())->toEqual($uncachedAuthor->count());
    expect($author->pluck("id"))->toEqual($uncachedAuthor->pluck("id"));
});
