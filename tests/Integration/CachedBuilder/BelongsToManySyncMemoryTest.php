<?php

declare(strict_types=1);

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Store;

test('sync completes without memory exhaustion', function () {
    $book = (new Book)
        ->disableModelCaching()
        ->first();
    $stores = Store::factory()->count(50)->create();
    $storeIds = $stores->pluck('id')->toArray();

    $memoryBefore = memory_get_usage(true);
    $result = $book->stores()->sync($storeIds);
    $memoryAfter = memory_get_usage(true);

    expect($result)->toBeArray();
    expect($result)->toHaveKey('attached');
    expect($result)->toHaveKey('detached');
    expect($result)->toHaveKey('updated');

    $memoryGrowth = $memoryAfter - $memoryBefore;
    expect($memoryGrowth)->toBeLessThan(
        10 * 1024 * 1024,
        "Memory grew by " . round($memoryGrowth / 1024 / 1024, 2) . "MB during sync — possible memory leak.",
    );
});

test('sync invalidates cache correctly', function () {
    $book = (new Book)
        ->disableModelCaching()
        ->first();
    $newStores = Store::factory()->count(3)->create();

    $cachedStores = Book::find($book->id)->stores;
    expect($cachedStores)->not->toBeNull();

    $book->stores()->sync($newStores->pluck('id'));

    $freshStores = Book::find($book->id)->stores;
    $freshStoreIds = $freshStores->pluck('id')->sort()->values()->toArray();
    $expectedIds = $newStores->pluck('id')->sort()->values()->toArray();

    expect($freshStoreIds)->toEqual($expectedIds);
});
