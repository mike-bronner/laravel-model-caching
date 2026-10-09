<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Post;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedPost;

test('eagerloaded relationship', function () {
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:posts:genealabslaravelmodelcachingtestsfixturespost-testing:{$this->testingSqlitePath}testing.sqlite:tags-first");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturespost",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturestag",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:posts",
    ];

    $result = (new Post)
        ->with("tags")
        ->first()
        ->tags;
    $cachedResults = $this->cache()
        ->tags($tags)
        ->get($key)['value'];
    $liveResults = (new UncachedPost)
        ->with("tags")
        ->first()
        ->tags;

    expect($result->pluck("id")->toArray())->toEqual($liveResults->pluck("id")->toArray());
    expect($cachedResults->pluck("id")->toArray())->toEqual($liveResults->pluck("id")->toArray());
    expect($result)->not->toBeEmpty();
    expect($cachedResults)->not->toBeEmpty();
    expect($liveResults)->not->toBeEmpty();
});

test('lazyloaded relationship', function () {
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:posts:genealabslaravelmodelcachingtestsfixturespost-testing:{$this->testingSqlitePath}testing.sqlite:tags-first");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturespost",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturestag",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:posts",
    ];

    $result = (new Post)
        ->with("tags")
        ->first()
        ->tags;
    $cachedResults = $this->cache()
        ->tags($tags)
        ->get($key)['value'];
    $liveResults = (new UncachedPost)
        ->with("tags")
        ->first()
        ->tags;

    expect($result->pluck("id")->toArray())->toEqual($liveResults->pluck("id")->toArray());
    expect($cachedResults->pluck("id")->toArray())->toEqual($liveResults->pluck("id")->toArray());
    expect($result)->not->toBeEmpty();
    expect($cachedResults)->not->toBeEmpty();
    expect($liveResults)->not->toBeEmpty();
});
