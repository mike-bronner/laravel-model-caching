<?php

use GeneaLabs\LaravelModelCaching\CachedBelongsToMany;
use GeneaLabs\LaravelModelCaching\CachedMorphToMany;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorWithCooldown;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Post;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

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

test('many to many relations are built uncached when caching is disabled', function () {
    config(['laravel-model-caching.enabled' => false]);

    $stores = (new Book)->first()->stores();
    $tags = (new Post)->first()->tags();

    expect($stores)->toBeInstanceOf(BelongsToMany::class)
        ->not->toBeInstanceOf(CachedBelongsToMany::class)
        ->and($tags)->toBeInstanceOf(MorphToMany::class)
        ->not->toBeInstanceOf(CachedMorphToMany::class);

    $stores->get();
    $tags->get();

    expect(modelCacheKeys())->toBe([]);
});

test('cooldown seconds are not recorded when caching is disabled', function () {
    $method = (new ReflectionClass(AuthorWithCooldown::class))->getMethod('getModelCacheCooldown');
    config(['laravel-model-caching.enabled' => false]);

    (new AuthorWithCooldown)->withCacheCooldownSeconds(5)->get();

    config(['laravel-model-caching.enabled' => true]);
    $author = new AuthorWithCooldown;

    expect($method->invokeArgs($author, [$author]))->toBe([null, null, null]);
});
