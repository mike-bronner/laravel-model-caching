<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\BookWithCooldown;

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
