<?php

declare(strict_types=1);

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
use Illuminate\Cache\Repository;

beforeEach(function () {
    useInMemoryDynamoDbStore();
});

test('model caching all uses dynamo db repository path', function () {
    $cachedAuthors = Author::all();
    $writesAfterFirstCall = FakeDynamoDbStore::writeCount();
    $authorId = $cachedAuthors->first()->id;

    expect(Author::all())->toEqual($cachedAuthors);
    expect(FakeDynamoDbStore::writeCount())->toBe($writesAfterFirstCall);

    UncachedAuthor::query()
        ->where('id', $authorId)
        ->update(['name' => 'DYNAMODB_ALL_UPDATED_AUTHOR']);

    ModelCache::invalidate(Author::class);

    $freshAuthors = Author::all();

    expect($freshAuthors->firstWhere('id', $authorId)->name)->toBe('DYNAMODB_ALL_UPDATED_AUTHOR');
});

test('repeated queries reuse cached results on dynamo db', function () {
    $first = Author::query()->get();
    $writesAfterFirstQuery = FakeDynamoDbStore::writeCount();
    $second = Author::query()->get();

    expect($second)->toEqual($first);
    expect(FakeDynamoDbStore::writeCount())->toBe($writesAfterFirstQuery);
});

test('has many through queries cache on dynamo db', function () {
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

    expect($eagerLoadedPrinters->pluck('id')->toArray())->toEqual(
        $liveEagerLoadedPrinters->pluck('id')->toArray(),
    );
    expect($lazyLoadedPrinters->pluck('id')->toArray())->toEqual(
        $liveLazyLoadedPrinters->pluck('id')->toArray(),
    );
});

test('has one through queries cache on dynamo db', function () {
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

    expect($eagerLoadedHistory->id)->toBe($liveEagerLoadedHistory->id);
    expect($lazyLoadedHistory->id)->toBe($liveLazyLoadedHistory->id);
});

test('model invalidation refreshes only the target model namespace', function () {
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

    expect($freshAuthors->firstWhere('id', $authorId)->name)->toBe('DYNAMODB_UPDATED_AUTHOR');
    expect($staleBooks->firstWhere('id', $bookId)->title)->not->toBe('DYNAMODB_UPDATED_BOOK');
});

test('clear command logically invalidates all entries without flushing store', function () {
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

    expect(FakeDynamoDbStore::flushCount())->toBe(0);
    expect($freshAuthors->firstWhere('id', $authorId)->name)->toBe('CLEARED_AUTHOR');
    expect($freshBooks->firstWhere('id', $bookId)->title)->toBe('CLEARED_BOOK');
});

test('cooldown metadata remains unversioned on dynamo db', function () {
    AuthorWithCooldown::query()
        ->withCacheCooldownSeconds(60)
        ->get();

    $keys = FakeDynamoDbStore::keys();

    expect(collect($keys)->contains(fn ($key) => str_contains($key, '-cooldown:seconds')))->toBeTrue();
    expect(collect($keys)->contains(fn ($key) => str_contains($key, '-cooldown:invalidated-at')))->toBeTrue();
    expect(collect($keys)->contains(fn ($key) => str_contains($key, '-cooldown:seconds:versions:')))->toBeFalse();
});

test('hash collision recovery rebuilds corrupted hashed entries on dynamo db', function () {
    $builder = Author::query();
    $expectedAuthors = $builder->get();
    $cacheKey = invokeProtectedMethod($builder, 'makeCacheKey', [['*']]);
    $cacheTags = invokeProtectedMethod($builder, 'makeCacheTags');
    $hashedItemKey = invokeProtectedMethod(
        ModelCacheRepository::make(),
        'itemKey',
        [$cacheKey, $cacheTags, true],
    );

    FakeDynamoDbStore::putStoredValue($hashedItemKey, [
        'key' => 'corrupted-cache-key',
        'value' => $expectedAuthors,
    ]);

    $freshAuthors = Author::query()->get();
    $storedPayload = $this->deserializeCacheValue(
        value: FakeDynamoDbStore::getStoredValue($hashedItemKey),
    );

    expect($freshAuthors->pluck('id')->toArray())->toEqual(
        $expectedAuthors->pluck('id')->toArray(),
    );
    expect($storedPayload['key'])->toBe($cacheKey);
    expect(collect($storedPayload['value'])->pluck('id')->toArray())->toEqual(
        $freshAuthors->pluck('id')->toArray(),
    );
});

test('pivot operations invalidate cached relations without flushing store', function () {
    $user = User::query()->first();
    $initialCount = $user->roles()->count();
    $newRole = Role::factory()->create();

    $user->roles()->attach($newRole->id);

    $freshCount = User::query()
        ->find($user->id)
        ->roles()
        ->count();

    expect($freshCount)->toBe($initialCount + 1);
    expect(FakeDynamoDbStore::flushCount())->toBe(0);
});

test('long and special character tags are hashed into stable control keys', function () {
    $repository = ModelCacheRepository::make();
    $tag = str_repeat('tag:[special]/?=+&|', 12);

    $repository->invalidateTags([$tag]);

    $versionKey = invokeProtectedMethod($repository, 'tagVersionKey', [$tag]);

    expect(FakeDynamoDbStore::keys())->toContain($versionKey);
    expect(substr($versionKey, strrpos($versionKey, ':') + 1))->toMatch('/^[a-f0-9]{40}$/');
});

test('successive tag invalidations use the latest namespace version', function () {
    $repository = ModelCacheRepository::make();
    $builder = Author::query();
    $cachedAuthors = $builder->get();
    $authorId = $cachedAuthors->first()->id;
    $cacheTags = invokeProtectedMethod($builder, 'makeCacheTags');
    $authorTag = collect($cacheTags)
        ->first(fn (string $tag) => str_contains($tag, 'testsfixturesauthor'));
    $versionKey = invokeProtectedMethod($repository, 'tagVersionKey', [$authorTag]);

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

    expect($secondVersion)->not->toBe($firstVersion);
    expect($freshAuthors->firstWhere('id', $authorId)->name)->toBe('SECOND_NAMESPACE_UPDATE');
});

test('instanceof dynamo db store detection works with a custom driver name', function () {
    useInMemoryDynamoDbStore('fake-native-dynamodb', FakeNativeDynamoDbStore::class);

    $repository = ModelCacheRepository::make();

    Author::all();
    $this->artisan('modelCache:clear')
        ->assertExitCode(0);

    expect($repository->usesDynamoDb())->toBeTrue();
    expect(FakeNativeDynamoDbStore::flushCount())->toBe(0);
});

// On DynamoDB a query key carries the global version and then one version
// per tag. The tags are filtered, deduplicated and sorted first, so the
// order a caller lists them in never changes the key. The versions are
// seeded so that the whole key can be spelled out.
test('versioned key joins the global and sorted tag versions', function () {
    $repository = ModelCacheRepository::make();
    $store = app('cache')->store('dynamodb-model');
    $store->forever(invokeProtectedMethod($repository, 'globalVersionKey'), 'global');
    $store->forever(invokeProtectedMethod($repository, 'tagVersionKey', ['tag-a']), 'version-a');
    $store->forever(invokeProtectedMethod($repository, 'tagVersionKey', ['tag-b']), 'version-b');

    $key = invokeProtectedMethod(
        $repository,
        'itemKey',
        ['query', ['tag-b', '', 'tag-a', 'tag-b'], false],
    );

    expect($key)->toBe('query:versions:global:version-a:version-b');
});

function useInMemoryDynamoDbStore(
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

function invokeProtectedMethod(
    object $target,
    string $method,
    array $arguments = [],
): mixed {
    $reflectionMethod = new ReflectionMethod($target, $method);

    return $reflectionMethod->invokeArgs($target, $arguments);
}
