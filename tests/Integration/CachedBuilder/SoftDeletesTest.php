<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;

test('with trashed is cached', function () {
    $author = (new UncachedAuthor)
        ->first();
    $author->delete();
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-find_1-withTrashed");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $deletedAuthor = (new Author)
        ->withTrashed()
        ->find($author->id);
    $cachedResults = $this
        ->cache()
        ->tags($tags)
        ->get($key)['value'];
    $deletedUncachedAuthor = (new UncachedAuthor)
        ->withTrashed()
        ->find($author->id);

    expect($deletedAuthor->toArray())->toEqual($cachedResults->toArray());
    expect($deletedUncachedAuthor->toArray())->toEqual($cachedResults->toArray());
});

test('without trashed is cached', function () {
    $author = (new UncachedAuthor)
        ->first();
    $author->delete();
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null-find_{$author->id}-withoutTrashed");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $result = (new Author)
        ->withoutTrashed()
        ->find($author->id);
    $cachedResult = $this
        ->cache()
        ->tags($tags)
        ->get($key)['value'];
    $uncachedResult = (new UncachedAuthor)
        ->withoutTrashed()
        ->find($author->id);

    expect($result)->toEqual($uncachedResult);
    expect($cachedResult)->toEqual($uncachedResult);
    expect($result)->toBeNull();
    expect($cachedResult)->toBeNull();
    expect($uncachedResult)->toBeNull();
});

test('only trashed is cached', function () {
    $author = (new UncachedAuthor)
        ->first();
    $author->delete();
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_notnull-find_{$author->id}-onlyTrashed");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $deletedAuthor = (new Author)
        ->onlyTrashed()
        ->find($author->id);
    $cachedResults = $this
        ->cache()
        ->tags($tags)
        ->get($key)['value'];
    $deletedUncachedAuthor = (new UncachedAuthor)
        ->onlyTrashed()
        ->find($author->id);

    expect($deletedAuthor->toArray())->toEqual($cachedResults->toArray());
    expect($deletedUncachedAuthor->toArray())->toEqual($cachedResults->toArray());
});
