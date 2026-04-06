<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelModelCaching\Tests\Integration;

use GeneaLabs\LaravelModelCaching\Cache\ModelCacheRepository;
use GeneaLabs\LaravelModelCaching\Facades\ModelCache;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorWithCooldown;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\FakeDynamoDbStore;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\FakeNativeDynamoDbStore;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Role;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Supplier;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedSupplier;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\User;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;
use Illuminate\Cache\Repository;
use ReflectionMethod;

class DynamoDbModelCachingTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->useInMemoryDynamoDbStore();
    }

    public function test_model_caching_all_uses_dynamo_db_repository_path(): void
    {
        $cachedAuthors = Author::all();
        $writesAfterFirstCall = FakeDynamoDbStore::writeCount();
        $authorId = $cachedAuthors->first()->id;

        $this->assertEquals($cachedAuthors, Author::all());
        $this->assertSame($writesAfterFirstCall, FakeDynamoDbStore::writeCount());

        UncachedAuthor::query()
            ->where('id', $authorId)
            ->update(['name' => 'DYNAMODB_ALL_UPDATED_AUTHOR']);

        ModelCache::invalidate(Author::class);

        $freshAuthors = Author::all();

        $this->assertSame(
            'DYNAMODB_ALL_UPDATED_AUTHOR',
            $freshAuthors->firstWhere('id', $authorId)->name,
        );
    }

    public function test_repeated_queries_reuse_cached_results_on_dynamo_db(): void
    {
        $first = Author::query()->get();
        $writesAfterFirstQuery = FakeDynamoDbStore::writeCount();
        $second = Author::query()->get();

        $this->assertEquals($first, $second);
        $this->assertSame($writesAfterFirstQuery, FakeDynamoDbStore::writeCount());
    }

    public function test_has_many_through_queries_cache_on_dynamo_db(): void
    {
        $eagerLoadedPrinters = Author::with('printers')
            ->first()
            ->printers;
        $lazyLoadedPrinters = Author::find(1)
            ->printers;
        $liveEagerLoadedPrinters = UncachedAuthor::with('printers')
            ->first()
            ->printers;
        $liveLazyLoadedPrinters = UncachedAuthor::find(1)
            ->printers;

        $this->assertEquals(
            $liveEagerLoadedPrinters->pluck('id')->toArray(),
            $eagerLoadedPrinters->pluck('id')->toArray(),
        );
        $this->assertEquals(
            $liveLazyLoadedPrinters->pluck('id')->toArray(),
            $lazyLoadedPrinters->pluck('id')->toArray(),
        );
    }

    public function test_has_one_through_queries_cache_on_dynamo_db(): void
    {
        $eagerLoadedHistory = Supplier::with('history')
            ->first()
            ->history;
        $lazyLoadedHistory = Supplier::first()
            ->history;
        $liveEagerLoadedHistory = UncachedSupplier::with('history')
            ->first()
            ->history;
        $liveLazyLoadedHistory = UncachedSupplier::first()
            ->history;

        $this->assertSame($liveEagerLoadedHistory->id, $eagerLoadedHistory->id);
        $this->assertSame($liveLazyLoadedHistory->id, $lazyLoadedHistory->id);
    }

    public function test_model_invalidation_refreshes_only_the_target_model_namespace(): void
    {
        $cachedAuthors = Author::query()->get();
        $cachedBooks = Book::query()->get();
        $authorId = $cachedAuthors->first()->id;
        $bookId = $cachedBooks->first()->id;

        UncachedAuthor::query()
            ->where('id', $authorId)
            ->update(['name' => 'DYNAMODB_UPDATED_AUTHOR']);
        UncachedBook::query()
            ->where('id', $bookId)
            ->update(['title' => 'DYNAMODB_UPDATED_BOOK']);

        ModelCache::invalidate(Author::class);

        $freshAuthors = Author::query()->get();
        $staleBooks = Book::query()->get();

        $this->assertSame(
            'DYNAMODB_UPDATED_AUTHOR',
            $freshAuthors->firstWhere('id', $authorId)->name,
        );
        $this->assertNotSame(
            'DYNAMODB_UPDATED_BOOK',
            $staleBooks->firstWhere('id', $bookId)->title,
        );
    }

    public function test_clear_command_logically_invalidates_all_entries_without_flushing_store(): void
    {
        $cachedAuthors = Author::query()->get();
        $cachedBooks = Book::query()->get();
        $authorId = $cachedAuthors->first()->id;
        $bookId = $cachedBooks->first()->id;

        UncachedAuthor::query()
            ->where('id', $authorId)
            ->update(['name' => 'CLEARED_AUTHOR']);
        UncachedBook::query()
            ->where('id', $bookId)
            ->update(['title' => 'CLEARED_BOOK']);

        $this->artisan('modelCache:clear')
            ->assertExitCode(0);

        $freshAuthors = Author::query()->get();
        $freshBooks = Book::query()->get();

        $this->assertSame(0, FakeDynamoDbStore::flushCount());
        $this->assertSame('CLEARED_AUTHOR', $freshAuthors->firstWhere('id', $authorId)->name);
        $this->assertSame('CLEARED_BOOK', $freshBooks->firstWhere('id', $bookId)->title);
    }

    public function test_cooldown_metadata_remains_unversioned_on_dynamo_db(): void
    {
        AuthorWithCooldown::query()
            ->withCacheCooldownSeconds(60)
            ->get();

        $keys = FakeDynamoDbStore::keys();

        $this->assertTrue(
            collect($keys)->contains(fn ($key) => str_contains($key, '-cooldown:seconds')),
        );
        $this->assertTrue(
            collect($keys)->contains(fn ($key) => str_contains($key, '-cooldown:invalidated-at')),
        );
        $this->assertFalse(
            collect($keys)->contains(fn ($key) => str_contains($key, '-cooldown:seconds:versions:')),
        );
    }

    public function test_hash_collision_recovery_rebuilds_corrupted_hashed_entries_on_dynamo_db(): void
    {
        $builder = Author::query();
        $expectedAuthors = $builder->get();
        $cacheKey = $this->invokeProtectedMethod($builder, 'makeCacheKey', [['*']]);
        $cacheTags = $this->invokeProtectedMethod($builder, 'makeCacheTags');
        $hashedItemKey = $this->invokeProtectedMethod(
            ModelCacheRepository::make(),
            'itemKey',
            [$cacheKey, $cacheTags, true],
        );

        FakeDynamoDbStore::putStoredValue($hashedItemKey, [
            'key' => 'corrupted-cache-key',
            'value' => $expectedAuthors,
        ]);

        $freshAuthors = Author::query()->get();
        $storedPayload = FakeDynamoDbStore::getStoredValue($hashedItemKey);

        $this->assertEquals($expectedAuthors->pluck('id')->toArray(), $freshAuthors->pluck('id')->toArray());
        $this->assertSame($cacheKey, $storedPayload['key']);
        $this->assertEquals(
            $freshAuthors->pluck('id')->toArray(),
            collect($storedPayload['value'])->pluck('id')->toArray(),
        );
    }

    public function test_pivot_operations_invalidate_cached_relations_without_flushing_store(): void
    {
        $user = User::query()->first();
        $initialCount = $user->roles()->count();
        $newRole = Role::factory()->create();

        $user->roles()->attach($newRole->id);

        $freshCount = User::query()
            ->find($user->id)
            ->roles()
            ->count();

        $this->assertSame($initialCount + 1, $freshCount);
        $this->assertSame(0, FakeDynamoDbStore::flushCount());
    }

    public function test_long_and_special_character_tags_are_hashed_into_stable_control_keys(): void
    {
        $repository = ModelCacheRepository::make();
        $tag = str_repeat('tag:[special]/?=+&|', 12);

        $repository->invalidateTags([$tag]);

        $versionKey = $this->invokeProtectedMethod($repository, 'tagVersionKey', [$tag]);

        $this->assertContains($versionKey, FakeDynamoDbStore::keys());
        $this->assertMatchesRegularExpression(
            '/^[a-f0-9]{40}$/',
            substr($versionKey, strrpos($versionKey, ':') + 1),
        );
    }

    public function test_successive_tag_invalidations_use_the_latest_namespace_version(): void
    {
        $repository = ModelCacheRepository::make();
        $builder = Author::query();
        $cachedAuthors = $builder->get();
        $authorId = $cachedAuthors->first()->id;
        $cacheTags = $this->invokeProtectedMethod($builder, 'makeCacheTags');
        $authorTag = collect($cacheTags)
            ->first(fn (string $tag) => str_contains($tag, 'testsfixturesauthor'));
        $versionKey = $this->invokeProtectedMethod($repository, 'tagVersionKey', [$authorTag]);

        UncachedAuthor::query()
            ->where('id', $authorId)
            ->update(['name' => 'FIRST_NAMESPACE_UPDATE']);
        $repository->invalidateTags($cacheTags);
        $firstVersion = FakeDynamoDbStore::getStoredValue($versionKey);

        UncachedAuthor::query()
            ->where('id', $authorId)
            ->update(['name' => 'SECOND_NAMESPACE_UPDATE']);
        $repository->invalidateTags($cacheTags);
        $secondVersion = FakeDynamoDbStore::getStoredValue($versionKey);

        $freshAuthors = Author::query()->get();

        $this->assertNotSame($firstVersion, $secondVersion);
        $this->assertSame(
            'SECOND_NAMESPACE_UPDATE',
            $freshAuthors->firstWhere('id', $authorId)->name,
        );
    }

    public function test_instanceof_dynamo_db_store_detection_works_with_a_custom_driver_name(): void
    {
        $this->useInMemoryDynamoDbStore('fake-native-dynamodb', FakeNativeDynamoDbStore::class);

        $repository = ModelCacheRepository::make();

        Author::all();
        $this->artisan('modelCache:clear')
            ->assertExitCode(0);

        $this->assertTrue($repository->usesDynamoDb());
        $this->assertSame(0, FakeNativeDynamoDbStore::flushCount());
    }

    private function useInMemoryDynamoDbStore(
        string $driver = 'dynamodb',
        string $storeClass = FakeDynamoDbStore::class,
    ): void {
        $storeClass::reset();

        app('cache')->extend($driver, function () use ($storeClass) {
            return new Repository(new $storeClass);
        });

        app('cache')->forgetDriver('dynamodb-model');

        config([
            'cache.stores.dynamodb-model' => ['driver' => $driver],
            'laravel-model-caching.store' => 'dynamodb-model',
        ]);
    }

    private function invokeProtectedMethod(
        object $target,
        string $method,
        array $arguments = [],
    ): mixed {
        $reflectionMethod = new ReflectionMethod($target, $method);
        $reflectionMethod->setAccessible(true);

        return $reflectionMethod->invokeArgs($target, $arguments);
    }
}
