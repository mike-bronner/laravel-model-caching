<?php

declare(strict_types=1);

use GeneaLabs\LaravelModelCaching\CachedBelongsToMany;
use GeneaLabs\LaravelModelCaching\CachedHasManyThrough;
use GeneaLabs\LaravelModelCaching\CachedHasOneThrough;
use GeneaLabs\LaravelModelCaching\CachedMorphToMany;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Post;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\PrefixedAuthor;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Store;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Supplier;
use Illuminate\Database\Eloquent\Collection;

test('the four subjects are the cached relation classes', function () {
    foreach (cachedRelations() as $expectedClass => $relation) {
        expect($relation)->toBeInstanceOf($expectedClass);
    }
});

test('get cache prefix returns a prefix on every cached relation', function () {
    foreach (cachedRelations() as $class => $relation) {
        expect(getCachePrefix($relation))->toBe(
            "genealabs:laravel-model-caching:",
            "{$class} should return a cache prefix rather than raising.",
        );
    }
});

test('get cache prefix resolves the related model not the parent', function () {
    $relation = (new Book)->first()->prefixedStores();

    expect($relation)->toBeInstanceOf(CachedBelongsToMany::class);
    expect(getCachePrefix($relation))->toBe("genealabs:laravel-model-caching:store-prefix:");
});

test('cached builder cache prefixes are unchanged', function () {
    expect(getCachePrefix((new Author)->newQuery()))->toBe("genealabs:laravel-model-caching:");
    expect(getCachePrefix((new PrefixedAuthor)->newQuery()))->toBe(
        "genealabs:laravel-model-caching:model-prefix:",
    );
});

test('flush cache does not raise on any cached relation', function () {
    foreach (cachedRelations() as $class => $relation) {
        $relation->flushCache();
    }

    expect(true)->toBeTrue("flushCache() raised on none of the four.");
});

test('flush cache on a relation invalidates the related models cache', function () {
    (new Store)->get();
    expect(cachedStoresPayload())->not->toBeNull();

    (new Book)->first()->stores()->flushCache();

    expect(cachedStoresPayload())->toBeNull();
});

test('all does not raise on a relation using builder caching', function () {
    $fromBelongsToMany = (new Book)->first()->stores()->all();
    $fromMorphToMany = (new Post)->first()->tags()->all();

    expect($fromBelongsToMany)->toBeInstanceOf(Collection::class);
    expect($fromMorphToMany)->toBeInstanceOf(Collection::class);
    expect($fromBelongsToMany)->not->toBeEmpty();
    expect($fromMorphToMany)->not->toBeEmpty();
});

test('truncate does not raise on a relation using builder caching', function () {
    expect((new Store)->get())->not->toBeEmpty();

    (new Book)->first()->stores()->truncate();

    expect((new Store)->get()->isEmpty())->toBeTrue();
});

function cachedRelations(): array
{
    return [
        CachedBelongsToMany::class => (new Book)->first()->stores(),
        CachedMorphToMany::class => (new Post)->first()->tags(),
        CachedHasManyThrough::class => (new Author)->first()->printers(),
        CachedHasOneThrough::class => (new Supplier)->first()->history(),
    ];
}

function getCachePrefix(object $subject): string
{
    return (new ReflectionMethod($subject, "getCachePrefix"))->invoke($subject);
}

function cachedStoresPayload()
{
    $testingSqlitePath = test()->testingSqlitePath;

    $key = sha1("genealabs:laravel-model-caching:testing:{$testingSqlitePath}testing.sqlite:stores:genealabslaravelmodelcachingtestsfixturesstore");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesstore",
        "genealabs:laravel-model-caching:testing:{$testingSqlitePath}testing.sqlite:stores",
    ];

    return test()->cache()->tags($tags)->get($key);
}
