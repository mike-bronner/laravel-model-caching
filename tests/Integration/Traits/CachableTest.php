<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\PrefixedAuthor;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;
use Illuminate\Database\Eloquent\Collection;

test('specifying alternate cache driver', function () {
    $configCacheStores = config('cache.stores');
    $configCacheStores['customCache'] = ['driver' => 'array'];
    // TODO: make sure the alternate cache is actually loaded
    config(['cache.stores' => $configCacheStores]);
    config(['laravel-model-caching.store' => 'customCache']);
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $authors = (new Author)
        ->all();
    $defaultcacheResults = $this
        ->makeCacheDeserializeProxy(store: app(abstract: "cache"))
        ->tags($tags)
        ->get($key)['value']
        ?? null;
    $customCacheResults = $this
        ->cache()
        ->tags($tags)
        ->get($key)['value']
        ?? null;
    $liveResults = (new UncachedAuthor)
        ->all();

    expect($authors)->toEqual($customCacheResults);
    expect($defaultcacheResults)->toBeNull();
    expect($liveResults->diffAssoc($customCacheResults))->toBeEmpty();
});

test('set cache prefix attribute', function () {
    (new PrefixedAuthor)->get();

    $results = $this->
        cache()
        ->tags([
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:model-prefix:genealabslaravelmodelcachingtestsfixturesprefixedauthor",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:model-prefix:authors",
        ])
        ->get(sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:model-prefix:authors:genealabslaravelmodelcachingtestsfixturesprefixedauthor-authors.deleted_at_null"))['value'];

    expect($results)->not->toBeNull();
});

test('all returns collection', function () {
    (new Author)->truncate();
    Author::factory()->count(1)->create();
    $authors = (new Author)->all();

    $cachedResults = $this
        ->cache()
        ->tags([
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
        ])
        ->get(sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null"))['value'];
    $liveResults = (new UncachedAuthor)->all();

    expect($authors)->toBeInstanceOf(Collection::class);
    expect($cachedResults)->toBeInstanceOf(Collection::class);
    expect($liveResults)->toBeInstanceOf(Collection::class);
});

test('s cache flag disables caching', function () {
    config(['laravel-model-caching.enabled' => false]);

    $authors = (new Author)->get();
    $cachedAuthors = $this
        ->cache()
        ->tags([
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesauthor",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
        ])
        ->get(sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors:genealabslaravelmodelcachingtestsfixturesauthor-authors.deleted_at_null"));

    config(['laravel-model-caching.enabled' => true]);

    expect($cachedAuthors)->toBeNull();
    expect($authors)->not->toBeEmpty();
    expect($authors)->toHaveCount(10);
});
