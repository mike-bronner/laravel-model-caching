<?php

namespace GeneaLabs\LaravelModelCaching\Tests\Integration\CachedBuilder;

use GeneaLabs\LaravelModelCaching\CachedBuilder;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorCachedQueryBuilder;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorExtendingGenerated;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorQueryBuilder;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorWithCachedCustomBuilder;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorWithCustomBuilder;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorWithTraitCollision;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;

class CustomBuilderTest extends IntegrationTestCase
{
    /**
     * AC 1: When caching is disabled (config off), models with a custom builder
     *       defined via static $builder receive that custom builder — not a plain
     *       EloquentBuilder and not a CachedBuilder.
     */
    public function test_non_cachable_model_uses_custom_builder()
    {
        // Disable caching globally so newEloquentBuilder() takes the non-cachable path.
        // Use try/finally so config is always restored even when an assertion fails.
        config(['laravel-model-caching.enabled' => false]);

        try {
            $builder = (new AuthorWithCustomBuilder)->newQuery();

            $this->assertInstanceOf(
                AuthorQueryBuilder::class,
                $builder,
                'Non-cachable model with static $builder must return the custom builder class',
            );
        } finally {
            config(['laravel-model-caching.enabled' => true]);
        }
    }

    /**
     * AC 2: When caching is enabled and the model's custom builder extends
     *       CachedBuilder, the custom CachedBuilder subclass is returned so
     *       both custom query methods AND caching are available.
     */
    public function test_cachable_model_with_cached_builder_subclass_uses_custom_builder()
    {
        $builder = (new AuthorWithCachedCustomBuilder)->newQuery();

        $this->assertInstanceOf(
            AuthorCachedQueryBuilder::class,
            $builder,
            'Cachable model whose custom builder extends CachedBuilder should return that builder',
        );

        // Custom method must be callable on the returned builder
        $this->assertTrue(
            method_exists($builder, 'famous'),
            'Custom query method famous() must be available on the returned builder',
        );
    }

    /**
     * AC 2 continued: the custom CachedBuilder subclass must still cache queries.
     */
    public function test_custom_cached_builder_subclass_still_caches_results()
    {
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

        $this->assertNotNull($cached, 'Custom CachedBuilder subclass must store results in cache');
        $this->assertEquals(
            $results->count(),
            $cached['value']->count(),
            'Cached count must match the live result count',
        );
    }

    /**
     * AC 3: When caching is enabled but the model's custom builder does NOT extend
     *       CachedBuilder, the package wraps it inside a CachedBuilder so caching
     *       is never silently dropped.
     */
    public function test_cachable_model_with_non_cached_builder_wraps_to_cached_builder()
    {
        $builder = (new AuthorWithCustomBuilder)->newQuery();

        $this->assertInstanceOf(
            CachedBuilder::class,
            $builder,
            'When custom builder does not extend CachedBuilder, newQuery() must return CachedBuilder',
        );

        // The inner builder should be the custom builder
        $this->assertInstanceOf(
            AuthorQueryBuilder::class,
            $builder->getInnerBuilder(),
            'The inner builder must be the custom AuthorQueryBuilder',
        );
    }

    public function test_wrapped_custom_builder_methods_are_callable()
    {
        $builder = (new AuthorWithCustomBuilder)->newQuery();

        $result = $builder->famous();

        $this->assertInstanceOf(
            CachedBuilder::class,
            $result,
            'Fluent custom method must return the outer CachedBuilder for chaining',
        );
    }

    public function test_wrapped_builder_still_caches_results()
    {
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

        $this->assertNotNull($cached, 'Wrapped CachedBuilder must store results in cache');
        $this->assertEquals(
            $results->count(),
            $cached['value']->count(),
        );
    }

    public function test_custom_method_then_get_is_cached_end_to_end()
    {
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

        $this->assertNotNull($cached, 'Custom method + get() must store results in cache');
        $this->assertEquals(
            $results->count(),
            $cached['value']->count(),
            'Cached count must match the live result count',
        );
    }

    /**
     * AC 6: No fatal error when Cachable is combined with another trait that also
     *       defines newEloquentBuilder.  The model resolves the collision via the
     *       `insteadof` keyword and delegates to newModelCachingEloquentBuilder().
     */
    public function test_trait_collision_resolves_without_fatal_error()
    {
        // Constructing the builder must not throw a PHP fatal error.
        $builder = (new AuthorWithTraitCollision)->newQuery();

        $this->assertInstanceOf(
            CachedBuilder::class,
            $builder,
            'Model with trait collision resolved via insteadof must return a CachedBuilder',
        );
    }

    public function test_trait_collision_model_still_caches_results()
    {
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

        $this->assertNotNull($cached, 'Model with resolved trait collision must still cache results');
        $this->assertEquals(
            $results->count(),
            $cached['value']->count(),
        );
    }

    public function test_inheritance_with_double_cachable_does_not_recurse()
    {
        $builder = (new AuthorExtendingGenerated)->newQuery();

        $this->assertInstanceOf(
            CachedBuilder::class,
            $builder,
            'Child model whose parent also uses Cachable must still return a CachedBuilder',
        );
    }

    public function test_inheritance_with_double_cachable_still_caches_results()
    {
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

        $this->assertNotNull($cached, 'Child model with double-Cachable inheritance must still cache results');
        $this->assertEquals(
            $results->count(),
            $cached['value']->count(),
        );
    }
}
