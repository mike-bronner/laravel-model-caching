<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\BookWithCooldown;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Store;

test('attach respects cooldown', function () {
    $storeId = newStoreId();
    $book = startCooldownAndCacheBooks();

    $book->stores()->attach($storeId);

    expect(booksTagEntrySets())->not->toBeEmpty();
});

test('detach respects cooldown', function () {
    $storeId = newStoreId();
    (new BookWithCooldown)->newQuery()->findOrFail(1)->stores()->attach($storeId);
    $book = startCooldownAndCacheBooks();

    $book->stores()->detach($storeId);

    expect(booksTagEntrySets())->not->toBeEmpty();
});

test('sync respects cooldown', function () {
    $storeId = newStoreId();
    $book = startCooldownAndCacheBooks();

    $book->stores()->sync([$storeId]);

    expect(booksTagEntrySets())->not->toBeEmpty();
});

test('update existing pivot respects cooldown', function () {
    $storeId = newStoreId();
    (new BookWithCooldown)->newQuery()->findOrFail(1)->stores()->attach($storeId);
    $book = startCooldownAndCacheBooks();

    $book->stores()->updateExistingPivot($storeId, ["test" => "updated"]);

    expect(booksTagEntrySets())->not->toBeEmpty();
});

test('pivot write without cooldown still flushes', function () {
    $storeId = newStoreId();
    (new BookWithCooldown)->orderBy("id")->pluck("title");
    expect(booksTagEntrySets())->not->toBeEmpty();

    (new BookWithCooldown)->newQuery()->findOrFail(1)->stores()->attach($storeId);

    expect(booksTagEntrySets())->toBeEmpty();
});

function startCooldownAndCacheBooks(): BookWithCooldown
{
    (new BookWithCooldown)->withCacheCooldownSeconds(60)->get();
    (new BookWithCooldown)->orderBy("id")->pluck("title");

    expect(booksTagEntrySets())->not->toBeEmpty();

    return (new BookWithCooldown)->newQuery()->findOrFail(1);
}

function newStoreId(): int
{
    return Store::factory()->create()->id;
}
