<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching\Tests\Integration\Traits;

use GeneaLabs\LaravelModelCaching\CachedBelongsToMany;
use GeneaLabs\LaravelModelCaching\CachedHasManyThrough;
use GeneaLabs\LaravelModelCaching\CachedHasOneThrough;
use GeneaLabs\LaravelModelCaching\CachedMorphToMany;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Post;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\PrefixedAuthor;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\PrefixedStore;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Store;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Supplier;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;
use Illuminate\Database\Eloquent\Collection;
use ReflectionMethod;

class RelationCacheModelTest extends IntegrationTestCase
{
    private function cachedRelations(): array
    {
        return [
            CachedBelongsToMany::class => (new Book)->first()->stores(),
            CachedMorphToMany::class => (new Post)->first()->tags(),
            CachedHasManyThrough::class => (new Author)->first()->printers(),
            CachedHasOneThrough::class => (new Supplier)->first()->history(),
        ];
    }

    private function getCachePrefix(object $subject): string
    {
        return (new ReflectionMethod($subject, "getCachePrefix"))->invoke($subject);
    }

    public function testTheFourSubjectsAreTheCachedRelationClasses(): void
    {
        foreach ($this->cachedRelations() as $expectedClass => $relation) {
            $this->assertInstanceOf($expectedClass, $relation);
        }
    }

    public function testGetCachePrefixReturnsAPrefixOnEveryCachedRelation(): void
    {
        foreach ($this->cachedRelations() as $class => $relation) {
            $this->assertSame(
                "genealabs:laravel-model-caching:",
                $this->getCachePrefix($relation),
                "{$class} should return a cache prefix rather than raising."
            );
        }
    }

    public function testGetCachePrefixResolvesTheRelatedModelNotTheParent(): void
    {
        $relation = (new Book)->first()->prefixedStores();

        $this->assertInstanceOf(CachedBelongsToMany::class, $relation);
        $this->assertSame(
            "genealabs:laravel-model-caching:store-prefix:",
            $this->getCachePrefix($relation)
        );
    }

    public function testCachedBuilderCachePrefixesAreUnchanged(): void
    {
        $this->assertSame(
            "genealabs:laravel-model-caching:",
            $this->getCachePrefix((new Author)->newQuery())
        );
        $this->assertSame(
            "genealabs:laravel-model-caching:model-prefix:",
            $this->getCachePrefix((new PrefixedAuthor)->newQuery())
        );
    }

    public function testFlushCacheDoesNotRaiseOnAnyCachedRelation(): void
    {
        foreach ($this->cachedRelations() as $class => $relation) {
            $relation->flushCache();
        }

        $this->assertTrue(true, "flushCache() raised on none of the four.");
    }

    public function testFlushCacheOnARelationInvalidatesTheRelatedModelsCache(): void
    {
        (new Store)->get();
        $this->assertNotNull($this->cachedStoresPayload());

        (new Book)->first()->stores()->flushCache();

        $this->assertNull($this->cachedStoresPayload());
    }

    private function cachedStoresPayload()
    {
        $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:stores:genealabslaravelmodelcachingtestsfixturesstore");
        $tags = [
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesstore",
            "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:stores",
        ];

        return $this->cache()->tags($tags)->get($key);
    }

    public function testAllDoesNotRaiseOnARelationUsingBuilderCaching(): void
    {
        $fromBelongsToMany = (new Book)->first()->stores()->all();
        $fromMorphToMany = (new Post)->first()->tags()->all();

        $this->assertInstanceOf(Collection::class, $fromBelongsToMany);
        $this->assertInstanceOf(Collection::class, $fromMorphToMany);
        $this->assertNotEmpty($fromBelongsToMany);
        $this->assertNotEmpty($fromMorphToMany);
    }

    public function testTruncateDoesNotRaiseOnARelationUsingBuilderCaching(): void
    {
        $this->assertNotEmpty((new Store)->get());

        (new Book)->first()->stores()->truncate();

        $this->assertTrue((new Store)->get()->isEmpty());
    }
}
