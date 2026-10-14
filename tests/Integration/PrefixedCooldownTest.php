<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorWithEmptyPrefixAndCooldown;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\BookWithCooldown;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\PrefixedAuthorWithCooldown;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\PrefixedBookWithCooldown;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\PrefixedStoreWithCooldown;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Store;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\StoreWithCoolingBooks;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;

test('a model write keeps the cache during the cooldown of a prefixed model', function (): void {
    (new PrefixedAuthorWithCooldown)
        ->withCacheCooldownSeconds()
        ->get();
    $before = prefixedAuthorNames(new PrefixedAuthorWithCooldown);
    $author = (new PrefixedAuthorWithCooldown)
        ->newQuery()
        ->findOrFail(1);

    $author->update(["name" => "Renamed during cooldown"]);

    $liveNames = prefixedAuthorNames(new UncachedAuthor);
    $cachedNames = prefixedAuthorNames(new PrefixedAuthorWithCooldown);

    expect($liveNames === $before)->toBeFalse();
    expect($cachedNames)->toEqual($before);
});

test('the cooldown of a prefixed model is stored under its prefix', function (): void {
    $author = new PrefixedAuthorWithCooldown;
    $key = "genealabs:laravel-model-caching:model-prefix::"
        . PrefixedAuthorWithCooldown::class
        . "-cooldown:seconds";

    $author->withCacheCooldownSeconds()
        ->get();

    $seconds = $author->cache()
        ->get($key);

    expect($seconds)->toEqual($author->cacheCooldownSeconds);
});

test('a pivot write keeps the cooldown of an unprefixed parent', function (): void {
    $store = PrefixedStoreWithCooldown::create(["address" => "1 Prefix Way", "name" => "Prefixed"]);
    $book = startCooldownAndCacheBooks();

    $storeId = $store->getKey();
    $book->prefixedStores()
        ->attach($storeId);

    expect(booksTagEntrySets() === [])->toBeFalse();
});

test('a pivot write keeps the cooldown of a parent with another prefix', function (): void {
    $store = PrefixedStoreWithCooldown::create(["address" => "1 Prefix Way", "name" => "Prefixed"]);
    $book = (new Book)->first();
    (new PrefixedStoreWithCooldown)
        ->withCacheCooldownSeconds()
        ->get();
    (new PrefixedStoreWithCooldown)
        ->orderBy("id")
        ->pluck("name");
    expect(storesTagEntrySets() === [])->toBeFalse();

    $bookId = $book->getKey();
    $store->books()
        ->attach($bookId);

    expect(storesTagEntrySets() === [])->toBeFalse();
});

test('flushing a relation records its save under the related cooldown key', function (): void {
    $book = new BookWithCooldown;
    $key = "genealabs:laravel-model-caching::" . BookWithCooldown::class . "-cooldown:saved-at";
    $book->withCacheCooldownSeconds()
        ->get();
    $store = (new Store)->first();
    $relation = $store->belongsToMany(BookWithCooldown::class, "book_store", "store_id", "book_id");

    $relation->flushCache();

    $savedAt = $book->cache()
        ->get($key);

    expect($savedAt === null)->toBeFalse();
});

test('a builder write keeps the cooldown of a prefixed model', function (Closure $write): void {
    $attributes = ["name" => "Cooling", "email" => "cooling@example.com"];
    $author = PrefixedAuthorWithCooldown::create($attributes);
    $before = startPrefixedAuthorCooldown();

    $write($author->getKey());

    $liveNames = prefixedAuthorNames(new UncachedAuthor);
    $cachedNames = prefixedAuthorNames(new PrefixedAuthorWithCooldown);

    expect($liveNames === $before)->toBeFalse();
    expect($cachedNames)->toEqual($before);
})->with([
    "update" => fn (int $id) => (new PrefixedAuthorWithCooldown)
        ->whereKey($id)
        ->update(["name" => "Renamed during cooldown"]),
    "delete" => fn (int $id) => (new PrefixedAuthorWithCooldown)
        ->whereKey($id)
        ->delete(),
    "force delete" => fn (int $id) => (new PrefixedAuthorWithCooldown)
        ->whereKey($id)
        ->forceDelete(),
]);

test('a to-many relation write keeps the cooldown of its prefixed model', function (): void {
    $author = (new PrefixedAuthorWithCooldown)->first();
    (new PrefixedBookWithCooldown)
        ->withCacheCooldownSeconds()
        ->get();
    $before = prefixedBookTitles(new PrefixedBookWithCooldown);

    $author->hasMany(PrefixedBookWithCooldown::class, "author_id")
        ->update(["title" => "Renamed during cooldown"]);

    $liveTitles = prefixedBookTitles(new UncachedBook);
    $cachedTitles = prefixedBookTitles(new PrefixedBookWithCooldown);

    expect($liveTitles === $before)->toBeFalse();
    expect($cachedTitles)->toEqual($before);
});

test('a pivot write keeps the cooldown of the related model', function (): void {
    $book = (new PrefixedBookWithCooldown)->first();
    $store = (new StoreWithCoolingBooks)->first();
    (new PrefixedBookWithCooldown)
        ->withCacheCooldownSeconds()
        ->get();
    prefixedBookTitles(new PrefixedBookWithCooldown);
    expect(booksTagEntrySets() === [])->toBeFalse();

    $bookId = $book->getKey();
    $store->books()
        ->attach($bookId);

    expect(booksTagEntrySets() === [])->toBeFalse();
});

test('an empty cache prefix keys the cooldown without the global prefix', function (): void {
    config(["laravel-model-caching.cache-prefix" => "global-prefix"]);
    $author = new AuthorWithEmptyPrefixAndCooldown;
    $key = "genealabs:laravel-model-caching::"
        . AuthorWithEmptyPrefixAndCooldown::class
        . "-cooldown:seconds";
    $author->withCacheCooldownSeconds()
        ->get();
    $before = prefixedAuthorNames(new AuthorWithEmptyPrefixAndCooldown);
    $first = (new AuthorWithEmptyPrefixAndCooldown)
        ->newQuery()
        ->findOrFail(1);

    $first->update(["name" => "Renamed during cooldown"]);

    $seconds = $author->cache()
        ->get($key);
    $liveNames = prefixedAuthorNames(new UncachedAuthor);
    $cachedNames = prefixedAuthorNames(new AuthorWithEmptyPrefixAndCooldown);

    expect($seconds)->toEqual($author->cacheCooldownSeconds);
    expect($liveNames === $before)->toBeFalse();
    expect(data_get($liveNames, 1))->toBe("Renamed during cooldown");
    expect($cachedNames)->toEqual($before);
});

test('a cooldown seeded under the old key does not hold a write after upgrade', function (): void {
    $author = new PrefixedAuthorWithCooldown;
    $oldPrefix = "genealabs:laravel-model-caching::" . PrefixedAuthorWithCooldown::class;
    $cache = $author->cache();
    $cache->forever("{$oldPrefix}-cooldown:seconds", $author->cacheCooldownSeconds);
    $cache->forever("{$oldPrefix}-cooldown:invalidated-at", now());
    prefixedAuthorNames(new PrefixedAuthorWithCooldown);
    $first = (new PrefixedAuthorWithCooldown)
        ->newQuery()
        ->findOrFail(1);

    $first->update(["name" => "Renamed after upgrade"]);

    $liveNames = prefixedAuthorNames(new UncachedAuthor);
    $cachedNames = prefixedAuthorNames(new PrefixedAuthorWithCooldown);

    expect($cachedNames)->toEqual($liveNames);
});
