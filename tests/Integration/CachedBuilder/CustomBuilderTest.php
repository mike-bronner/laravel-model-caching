<?php

use GeneaLabs\LaravelModelCaching\CachedBuilder;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorCachedQueryBuilder;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorExtendingGenerated;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorQueryBuilder;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorWithCachedCustomBuilder;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorWithCustomBuilder;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorWithTraitCollision;

/**
 * AC 1: When caching is disabled (config off), models with a custom builder
 *       defined via static $builder receive that custom builder — not a plain
 *       EloquentBuilder and not a CachedBuilder.
 */
test('non cachable model uses custom builder', function () {
    // Disable caching globally so newEloquentBuilder() takes the non-cachable path.
    // Use try/finally so config is always restored even when an assertion fails.
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

/**
 * AC 2: When caching is enabled and the model's custom builder extends
 *       CachedBuilder, the custom CachedBuilder subclass is returned so
 *       both custom query methods AND caching are available.
 */
test('cachable model with cached builder subclass uses custom builder', function () {
    $builder = (new AuthorWithCachedCustomBuilder)->newQuery();

    expect($builder)->toBeInstanceOf(
        AuthorCachedQueryBuilder::class,
        'Cachable model whose custom builder extends CachedBuilder should return that builder',
    );

    // Custom method must be callable on the returned builder
    expect(method_exists($builder, 'famous'))->toBeTrue(
        'Custom query method famous() must be available on the returned builder',
    );
});

/**
 * AC 2 continued: the custom CachedBuilder subclass must still cache queries.
 */
test('custom cached builder subclass still caches results', function () {
    // Warm the cache
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

/**
 * AC 3: When caching is enabled but the model's custom builder does NOT extend
 *       CachedBuilder, the package wraps it inside a CachedBuilder so caching
 *       is never silently dropped.
 */
test('cachable model with non cached builder wraps to cached builder', function () {
    $builder = (new AuthorWithCustomBuilder)->newQuery();

    expect($builder)->toBeInstanceOf(
        CachedBuilder::class,
        'When custom builder does not extend CachedBuilder, newQuery() must return CachedBuilder',
    );

    // The inner builder should be the custom builder
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

/**
 * AC 6: No fatal error when Cachable is combined with another trait that also
 *       defines newEloquentBuilder.  The model resolves the collision via the
 *       `insteadof` keyword and delegates to newModelCachingEloquentBuilder().
 */
test('trait collision resolves without fatal error', function () {
    // Constructing the builder must not throw a PHP fatal error.
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
