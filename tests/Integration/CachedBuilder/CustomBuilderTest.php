<?php

use GeneaLabs\LaravelModelCaching\CachedBuilder;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorCachedQueryBuilder;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorExtendingGenerated;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorQueryBuilder;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorWithCachedCustomBuilder;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorWithCustomBuilder;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorWithTraitCollision;

test('non cachable model uses custom builder', function () {
    config(['laravel-model-caching.enabled' => false]);

    try {
        $builder = (new AuthorWithCustomBuilder)->newQuery();

        expect($builder)->toBeInstanceOf(
            AuthorQueryBuilder::class,
            'Non-cachable model with static $builder must return the custom builder class',
        );
    } finally {
        config(['laravel-model-caching.enabled' => true]);
    }
});

test('cachable model with cached builder subclass uses custom builder', function () {
    $builder = (new AuthorWithCachedCustomBuilder)->newQuery();

    expect($builder)->toBeInstanceOf(
        AuthorCachedQueryBuilder::class,
        'Cachable model whose custom builder extends CachedBuilder should return that builder',
    );

    expect(method_exists($builder, 'famous'))->toBeTrue(
        'Custom query method famous() must be available on the returned builder',
    );
});

test('custom cached builder subclass still caches results', function () {
    $results = (new AuthorWithCachedCustomBuilder)->get();

    $cacheKey = sha1(
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite" .
        ":authors:genealabslaravelmodelcachingtestsfixturesauthorwithcachedcustombuilder" .
        "-authors.deleted_at_null",
    );
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite" .
        ":genealabslaravelmodelcachingtestsfixturesauthorwithcachedcustombuilder",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $cached = $this->cache()->tags($tags)->get($cacheKey);

    expect($cached)->not->toBeNull('Custom CachedBuilder subclass must store results in cache');
    expect($cached['value']->count())->toEqual(
        $results->count(),
        'Cached count must match the live result count',
    );
});

test('cachable model with non cached builder wraps to cached builder', function () {
    $builder = (new AuthorWithCustomBuilder)->newQuery();

    expect($builder)->toBeInstanceOf(
        CachedBuilder::class,
        'When custom builder does not extend CachedBuilder, newQuery() must return CachedBuilder',
    );

    expect($builder->getInnerBuilder())->toBeInstanceOf(
        AuthorQueryBuilder::class,
        'The inner builder must be the custom AuthorQueryBuilder',
    );
});

test('wrapped custom builder methods are callable', function () {
    $builder = (new AuthorWithCustomBuilder)->newQuery();

    $result = $builder->famous();

    expect($result)->toBeInstanceOf(
        CachedBuilder::class,
        'Fluent custom method must return the outer CachedBuilder for chaining',
    );
});

test('wrapped builder still caches results', function () {
    $results = (new AuthorWithCustomBuilder)->get();

    $cacheKey = sha1(
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite" .
        ":authors:genealabslaravelmodelcachingtestsfixturesauthorwithcustombuilder" .
        "-authors.deleted_at_null",
    );
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite" .
        ":genealabslaravelmodelcachingtestsfixturesauthorwithcustombuilder",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $cached = $this->cache()->tags($tags)->get($cacheKey);

    expect($cached)->not->toBeNull('Wrapped CachedBuilder must store results in cache');
    expect($cached['value']->count())->toEqual($results->count());
});

test('custom method then get is cached end to end', function () {
    $results = (new AuthorWithCustomBuilder)->famous()->get();

    $cacheKey = sha1(
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite" .
        ":authors:genealabslaravelmodelcachingtestsfixturesauthorwithcustombuilder" .
        "-is_famous_=_1-authors.deleted_at_null-famous",
    );
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite" .
        ":genealabslaravelmodelcachingtestsfixturesauthorwithcustombuilder",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $cached = $this->cache()->tags($tags)->get($cacheKey);

    expect($cached)->not->toBeNull('Custom method + get() must store results in cache');
    expect($cached['value']->count())->toEqual(
        $results->count(),
        'Cached count must match the live result count',
    );
});

test('trait collision resolves without fatal error', function () {
    $builder = (new AuthorWithTraitCollision)->newQuery();

    expect($builder)->toBeInstanceOf(
        CachedBuilder::class,
        'Model with trait collision resolved via insteadof must return a CachedBuilder',
    );
});

test('trait collision model still caches results', function () {
    $results = (new AuthorWithTraitCollision)->get();

    $cacheKey = sha1(
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite" .
        ":authors:genealabslaravelmodelcachingtestsfixturesauthorwithtraitcollision" .
        "-authors.deleted_at_null",
    );
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite" .
        ":genealabslaravelmodelcachingtestsfixturesauthorwithtraitcollision",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $cached = $this->cache()->tags($tags)->get($cacheKey);

    expect($cached)->not->toBeNull('Model with resolved trait collision must still cache results');
    expect($cached['value']->count())->toEqual($results->count());
});

test('inheritance with double cachable does not recurse', function () {
    $builder = (new AuthorExtendingGenerated)->newQuery();

    expect($builder)->toBeInstanceOf(
        CachedBuilder::class,
        'Child model whose parent also uses Cachable must still return a CachedBuilder',
    );
});

test('inheritance with double cachable still caches results', function () {
    $results = (new AuthorExtendingGenerated)->get();

    $cacheKey = sha1(
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite" .
        ":authors:genealabslaravelmodelcachingtestsfixturesauthorextendinggenerated" .
        "-authors.deleted_at_null",
    );
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite" .
        ":genealabslaravelmodelcachingtestsfixturesauthorextendinggenerated",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors",
    ];

    $cached = $this->cache()->tags($tags)->get($cacheKey);

    expect($cached)->not->toBeNull(
        'Child model with double-Cachable inheritance must still cache results',
    );
    expect($cached['value']->count())->toEqual($results->count());
});
