<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;

test('closure runs with cache disabled', function () {
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $authors = app("model-cache")->runDisabled(function () {
        return (new Author)
            ->get();
    });

    $cachedResults1 = $this->cache()
        ->tags($tags)
        ->get($key)["value"]
        ?? null;
    (new Author)
        ->get();
    $cachedResults2 = $this->cache()
        ->tags($tags)
        ->get($key)["value"]
        ?? null;
    $liveResults = (new UncachedAuthor)
        ->get();

    expect($authors->toArray())->toEqual($liveResults->toArray());
    expect($cachedResults1)->toBeNull();
    expect($cachedResults2->toArray())->toEqual($authors->toArray());
});

test('caching is re enabled when the closure throws', function () {
    $thrown = null;

    try {
        app("model-cache")->runDisabled(function () {
            throw new \RuntimeException("closure failed");
        });
    } catch (\RuntimeException $exception) {
        $thrown = $exception;
    }

    expect($thrown?->getMessage())->toBe("closure failed");
    expect(config("laravel-model-caching.enabled"))->toBeTrue();
    expect((new Author)->isCachable())->toBeTrue();
});

test('writes inside the closure need invalidating afterwards', function () {
    $before = (new Author)->orderBy("id")->pluck("name");

    app("model-cache")->runDisabled(function () {
        $author = (new Author)->findOrFail(1);
        $author->name = "renamed while disabled";
        $author->save();
    });

    $staleNames = (new Author)->orderBy("id")->pluck("name");
    app("model-cache")->invalidate(Author::class);
    $freshNames = (new Author)->orderBy("id")->pluck("name");
    $liveNames = (new UncachedAuthor)->orderBy("id")->pluck("name");

    expect($staleNames)->toEqual($before);
    expect($liveNames)->not->toEqual($before);
    expect($freshNames)->toEqual($liveNames);
});
