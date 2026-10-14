<?php

declare(strict_types=1);

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorOptedOutOfTtl;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorWithDefaultEagerLoads;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorWithInvalidTtl;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorWithStringTtl;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorWithTtl;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorWithTtlRelations;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\BookWithInvalidTtlAuthor;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\BookWithTtl;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Comment;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\FakeDynamoDbStore;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\PostWithTtl;
use Illuminate\Support\Facades\DB;

test('a global ttl serves the cache until it passes, then reads fresh', function () {
    useArrayModelCacheStore();
    config(['laravel-model-caching.ttl' => 60]);

    expectRefreshAfterTtl(
        fn () => (new Author)->get()->pluck('name'),
        fn () => DB::table('authors')->update(['name' => 'TTL_RENAMED']),
        60,
    );
});

test('a numeric string global ttl works like its integer value', function () {
    useArrayModelCacheStore();
    config(['laravel-model-caching.ttl' => '60']);

    expectRefreshAfterTtl(
        fn () => (new Author)->get()->pluck('name'),
        fn () => DB::table('authors')->update(['name' => 'TTL_RENAMED']),
        60,
    );
});

test('a per-model ttl overrides a longer global ttl', function () {
    useArrayModelCacheStore();
    config(['laravel-model-caching.ttl' => 100]);

    expectRefreshAfterTtl(
        fn () => (new AuthorWithTtl)->get()->pluck('name'),
        fn () => DB::table('authors')->update(['name' => 'TTL_RENAMED']),
        30,
    );
});

test('a per-model ttl applies with no global ttl', function () {
    useArrayModelCacheStore();

    expectRefreshAfterTtl(
        fn () => (new AuthorWithTtl)->get()->pluck('name'),
        fn () => DB::table('authors')->update(['name' => 'TTL_RENAMED']),
        30,
    );
});

test('a numeric string per-model ttl works like its integer value', function () {
    useArrayModelCacheStore();

    expectRefreshAfterTtl(
        fn () => (new AuthorWithStringTtl)->get()->pluck('name'),
        fn () => DB::table('authors')->update(['name' => 'TTL_RENAMED']),
        30,
    );
});

test('a per-model ttl of 0 opts the model out of the global ttl', function () {
    useArrayModelCacheStore();
    config(['laravel-model-caching.ttl' => 60]);

    expectNoExpiry(
        fn () => (new AuthorOptedOutOfTtl)->get()->pluck('name'),
        fn () => DB::table('authors')->update(['name' => 'TTL_RENAMED']),
    );
});

test('a query caches forever when no ttl is set at either level', function () {
    useArrayModelCacheStore();

    expectNoExpiry(
        fn () => (new Author)->get()->pluck('name'),
        fn () => DB::table('authors')->update(['name' => 'TTL_RENAMED']),
    );
});

test('a ttl applies to each cached read path', function (Closure $read) {
    useArrayModelCacheStore();
    config(['laravel-model-caching.ttl' => 60]);

    expectRefreshAfterTtl(
        $read,
        fn () => DB::table('authors')->update(['name' => 'TTL_RENAMED']),
        60,
    );
})->with([
    'all' => fn () => Author::all()->pluck('name'),
    'count' => fn () => (new Author)->where('name', 'TTL_RENAMED')->count(),
    'find' => fn () => (new Author)->find(1)->name,
    'first' => fn () => (new Author)->orderBy('id')->first()->name,
    'paginate' => fn () => (new Author)->paginate(50)->pluck('name'),
]);

test('an eager-loaded model with a ttl expires the query that loads it', function () {
    useArrayModelCacheStore();

    expectRefreshAfterTtl(
        fn () => (new AuthorWithTtlRelations)->with('booksWithTtl')->get()
            ->pluck('booksWithTtl.*.title'),
        fn () => DB::table('books')->update(['title' => 'TTL_RETITLED']),
        30,
    );
});

test('a nested eager-loaded model with a ttl expires the query that loads it', function () {
    useArrayModelCacheStore();

    expectRefreshAfterTtl(
        fn () => (new AuthorWithTtlRelations)->with('booksWithPublisherWithTtl.publisher')->get()
            ->pluck('booksWithPublisherWithTtl.*.publisher.name'),
        fn () => DB::table('publishers')->update(['name' => 'TTL_RENAMED']),
        20,
    );
});

test('a latestOfMany eager load with a ttl expires the query that loads it', function () {
    useArrayModelCacheStore();

    expectRefreshAfterTtl(
        fn () => (new AuthorWithTtlRelations)->with('newestBookWithTtl')->get()
            ->pluck('newestBookWithTtl.title'),
        fn () => DB::table('books')->update(['title' => 'TTL_RETITLED']),
        30,
    );
});

test('a morphTo eager load uses the ttl of the morphed model', function () {
    useArrayModelCacheStore();
    $postId = (new PostWithTtl)->first()->id;
    insertMorphMemoComment(PostWithTtl::class, $postId);

    expectRefreshAfterTtl(
        fn () => (new Comment)->with('commentable')->get()
            ->where('commentable_type', PostWithTtl::class)
            ->pluck('commentable.title'),
        fn () => DB::table('posts')->where('id', $postId)->update(['title' => 'TTL_RETITLED']),
        30,
    );
});

test('the shortest ttl among the root and its eager loads wins', function () {
    useArrayModelCacheStore();

    expectRefreshAfterTtl(
        fn () => (new AuthorWithTtl)->with('booksWithPublisherWithTtl.publisher')->get()
            ->pluck('booksWithPublisherWithTtl.*.publisher.name'),
        fn () => DB::table('publishers')->update(['name' => 'TTL_RENAMED']),
        20,
    );
});

test('an eager-loaded model without a ttl does not lower the root ttl', function () {
    useArrayModelCacheStore();

    expectRefreshAfterTtl(
        fn () => (new AuthorWithTtl)->with('booksWithoutTtl')->get()->pluck('name'),
        fn () => DB::table('authors')->update(['name' => 'TTL_RENAMED']),
        30,
    );
});

test('a root opted out with 0 still takes the ttl of an eager-loaded model', function () {
    useArrayModelCacheStore();

    expectRefreshAfterTtl(
        fn () => (new AuthorOptedOutOfTtl)->with('booksWithTtl')->get()
            ->pluck('booksWithTtl.*.title'),
        fn () => DB::table('books')->update(['title' => 'TTL_RETITLED']),
        30,
    );
});

test('an eager-loaded model opted out with 0 does not force the root to forever', function () {
    useArrayModelCacheStore();
    config(['laravel-model-caching.ttl' => 60]);

    expectRefreshAfterTtl(
        fn () => (new AuthorWithTtlRelations)->with('booksOptedOutOfTtl')->get()->pluck('name'),
        fn () => DB::table('authors')->update(['name' => 'TTL_RENAMED']),
        60,
    );
});

test('a has-many-through relation stores its result with the related model ttl', function () {
    useArrayModelCacheStore();

    expectRefreshAfterTtl(
        fn () => (new AuthorWithTtlRelations)->find(1)->printersWithTtl()->get()->pluck('name'),
        fn () => DB::table('printers')->update(['name' => 'TTL_RENAMED']),
        30,
    );
});

test('a ttl expires cached queries on dynamo db', function () {
    useInMemoryDynamoDbStore();
    config(['laravel-model-caching.ttl' => 60]);
    (new Author)->get();
    $writesAfterFirstQuery = FakeDynamoDbStore::writeCount();

    expectRefreshAfterTtl(
        fn () => (new Author)->get()->pluck('name'),
        fn () => DB::table('authors')->update(['name' => 'TTL_RENAMED']),
        60,
    );
    expect(FakeDynamoDbStore::writeCount())->toBeGreaterThan($writesAfterFirstQuery);
});

test('a ttl sets an expiry on the redis cache item', function () {
    config(['laravel-model-caching.ttl' => 60]);

    (new Author)->get();

    $ttls = redisModelCacheItemTtls();

    expect($ttls)->not->toBeEmpty()
        ->and(min($ttls))->toBeGreaterThan(0)
        ->and(max($ttls))->toBeLessThanOrEqual(60);
});

test('no ttl leaves the redis cache item without an expiry', function () {
    (new Author)->get();

    $ttls = redisModelCacheItemTtls();

    expect($ttls)->not->toBeEmpty()
        ->and(array_unique($ttls))->toBe([-1]);
});

test('an invalid per-model ttl fails the query with a message naming the model and value', function () {
    expect(fn () => (new AuthorWithInvalidTtl)->get())
        ->toThrow(InvalidArgumentException::class, AuthorWithInvalidTtl::class)
        ->toThrow(InvalidArgumentException::class, "'60s'");
});

test('an invalid global ttl fails the query with a message naming the value', function () {
    config(['laravel-model-caching.ttl' => 'abc']);

    expect(fn () => (new Author)->get())
        ->toThrow(InvalidArgumentException::class, "MODEL_CACHE_TTL")
        ->toThrow(InvalidArgumentException::class, "'abc'");
});

test('a valid ttl value resolves to its seconds', function (mixed $ttl, ?int $seconds) {
    config(['laravel-model-caching.ttl' => $ttl]);
    expect(resolvedCacheTtl(modelWithCacheTtl(null)))->toBe($seconds);

    config(['laravel-model-caching.ttl' => null]);
    expect(resolvedCacheTtl(modelWithCacheTtl($ttl)))->toBe($seconds);
})->with([
    'null' => [null, null],
    'an empty string' => ['', null],
    'integer 0' => [0, null],
    'string 0' => ['0', null],
    'an integer' => [3600, 3600],
    'a numeric string' => ['3600', 3600],
]);

test('an invalid ttl value is rejected at both levels', function (mixed $ttl, string $shownValue) {
    config(['laravel-model-caching.ttl' => $ttl]);

    expect(fn () => resolvedCacheTtl(modelWithCacheTtl(null)))
        ->toThrow(InvalidArgumentException::class, "MODEL_CACHE_TTL")
        ->toThrow(InvalidArgumentException::class, "Got {$shownValue}.");

    config(['laravel-model-caching.ttl' => null]);
    $model = modelWithCacheTtl($ttl);

    expect(fn () => resolvedCacheTtl($model))
        ->toThrow(InvalidArgumentException::class, $model::class)
        ->toThrow(InvalidArgumentException::class, "Got {$shownValue}.");
})->with([
    'a non-numeric string' => ['abc', "'abc'"],
    'a negative string' => ['-10', "'-10'"],
    'a string with a unit' => ['60s', "'60s'"],
    'a decimal string' => ['1.9', "'1.9'"],
    'a padded string' => [' 60', "' 60'"],
    'a negative integer' => [-5, '-5'],
    'a float' => [1.9, '1.9'],
    'true' => [true, 'true'],
    'false' => [false, 'false'],
]);

test('an invalid per-model ttl is rejected even when a global ttl is set', function () {
    config(['laravel-model-caching.ttl' => 60]);

    expect(fn () => resolvedCacheTtl(modelWithCacheTtl(-5)))
        ->toThrow(InvalidArgumentException::class, "Got -5.");
});

test('an invalid ttl on an eager-loaded model fails the query that loads it', function () {
    expect(fn () => (new BookWithInvalidTtlAuthor)->with('author')->get())
        ->toThrow(InvalidArgumentException::class, AuthorWithInvalidTtl::class);
});

test('all() takes the ttl of the default eager loads in $with', function () {
    useArrayModelCacheStore();

    expectRefreshAfterTtl(
        fn () => AuthorWithDefaultEagerLoads::all()->pluck('booksWithTtl.*.title'),
        fn () => DB::table('books')->update(['title' => 'TTL_RETITLED']),
        30,
    );
});

test('all() is flushed by an eloquent write to a model in its default eager loads', function () {
    $cached = AuthorWithDefaultEagerLoads::all()->pluck('booksWithTtl.*.title');
    $book = (new BookWithTtl)->first();
    $book->title = 'FLUSHED_TITLE';
    $book->save();

    expect(AuthorWithDefaultEagerLoads::all()->pluck('booksWithTtl.*.title')->flatten())
        ->toContain('FLUSHED_TITLE')
        ->and($cached->flatten())->not->toContain('FLUSHED_TITLE');
});
