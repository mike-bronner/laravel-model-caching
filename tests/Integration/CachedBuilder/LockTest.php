<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;

test('lock for update bypasses cache', function () {
    $cachedAuthors = (new Author)->get();

    $lockedAuthors = (new Author)->lockForUpdate()->get();

    $uncachedAuthors = (new UncachedAuthor)->get();

    expect($uncachedAuthors->diffKeys($lockedAuthors))->toBeEmpty();
});

test('lock for update does not store result in cache', function () {
    $this->cache()->flush();

    $lockedAuthors = (new Author)->lockForUpdate()->get();

    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $cachedResult = $this->cache()->tags($tags)->get($key);

    expect($cachedResult)->toBeNull();
});

test('shared lock bypasses cache', function () {
    $cachedAuthors = (new Author)->get();

    $lockedAuthors = (new Author)->sharedLock()->get();

    $uncachedAuthors = (new UncachedAuthor)->get();

    expect($uncachedAuthors->diffKeys($lockedAuthors))->toBeEmpty();
});

test('shared lock does not store result in cache', function () {
    $this->cache()->flush();

    $lockedAuthors = (new Author)->sharedLock()->get();

    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $cachedResult = $this->cache()->tags($tags)->get($key);

    expect($cachedResult)->toBeNull();
});
