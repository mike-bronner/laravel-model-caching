<?php

declare(strict_types=1);

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\BookWithUncachedStore;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Store;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;

test('lazy loading relationship', function () {
    $bookId = (new Store)
        ->disableModelCaching()
        ->with("books")
        ->first()
        ->books
        ->first()
        ->id;
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:stores:genealabslaravelmodelcachingcachedbelongstomany-join_inner_book_store_f6999d9f3303-book_store.book_id_=_{$bookId}");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesstore",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:stores",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:book-store",
    ];

    $stores = (new Book)
        ->find($bookId)
        ->stores;
    $cachedStores = $this
        ->cache()
        ->tags($tags)
        ->get($key)['value'];
    $uncachedBook = (new UncachedBook)
        ->find($bookId);
    $uncachedStores = $uncachedBook->stores;

    expect($stores->pluck("id"))->toEqual($uncachedStores->pluck("id"));
    expect($cachedStores->pluck("id"))->toEqual($uncachedStores->pluck("id"));
    expect($cachedStores)->not->toBeNull();
    expect($uncachedStores)->not->toBeNull();
});

test('invalidating cache when attaching', function () {
    $bookId = (new Store)
        ->disableModelCaching()
        ->with("books")
        ->first()
        ->books
        ->first()
        ->id;
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:stores:genealabslaravelmodelcachingtestsfixturesstore-testing:{$this->testingSqlitePath}testing.sqlite:books-first");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesstore",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:stores",
    ];
    $newStore = Store::factory()->create();
    $result = (new Book)
        ->find($bookId)
        ->stores;

    (new Book)
        ->find($bookId)
        ->stores()
        ->attach($newStore->id);
    $cachedResult = $this
        ->cache()
        ->tags($tags)
        ->get($key)['value']
        ?? null;

    expect($result)->not->toBeEmpty();
    expect($cachedResult)->toBeNull();
});

test('invalidating cache when detaching', function () {
    $bookId = (new Store)
        ->disableModelCaching()
        ->with("books")
        ->first()
        ->books
        ->first()
        ->id;
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:book-store:genealabslaravelmodelcachingcachedbelongstomany-book_store.book_id_=_{$bookId}");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesstore",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:stores",
    ];
    $result = (new Book)
        ->find($bookId)
        ->stores;

    (new Book)
        ->find($bookId)
        ->stores()
        ->detach($result->first()->id);
    $cachedResult = $this
        ->cache()
        ->tags($tags)
        ->get($key)['value']
        ?? null;

    expect($result)->not->toBeEmpty();
    expect($cachedResult)->toBeNull();
});

test('invalidating cache when updating', function () {
    $bookId = (new Store)
        ->disableModelCaching()
        ->with("books")
        ->first()
        ->books
        ->first()
        ->id;
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:book-store:genealabslaravelmodelcachingcachedbelongstomany-book_store.book_id_=_{$bookId}");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesstore",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:stores",
    ];
    $result = (new Book)
        ->find($bookId)
        ->stores;

    $store = $result->first();
    $store->address = "test address";
    $store->save();
    $cachedResult = $this
        ->cache()
        ->tags($tags)
        ->get($key)['value']
        ?? null;

    expect($result)->not->toBeEmpty();
    expect($cachedResult)->toBeNull();
});

test('uncached related model doesnt cache', function () {
    $bookId = (new Store)
        ->disableModelCaching()
        ->with("books")
        ->first()
        ->books
        ->first()
        ->id;
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:book-store:genealabslaravelmodelcachingcachedbelongstomany-book_store.book_id_=_{$bookId}");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesuncachedstore",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:stores",
    ];

    $result = (new BookWithUncachedStore)
        ->find($bookId)
        ->uncachedStores;
    $cachedResult = $this
        ->cache()
        ->tags($tags)
        ->get($key)['value']
        ?? null;
    $uncachedResult = (new UncachedBook)
        ->find($bookId)
        ->stores;

    expect($result->pluck("id"))->toEqual($uncachedResult->pluck("id"));
    expect($cachedResult)->toBeNull();
    expect($result)->not->toBeNull();
    expect($uncachedResult)->not->toBeNull();
});

test('invalidating cache when syncing', function () {
    $bookId = (new Store)
        ->disableModelCaching()
        ->with("books")
        ->first()
        ->books
        ->first()
        ->id;
    $key = sha1("genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:stores:genealabslaravelmodelcachingtestsfixturesstore-testing:{$this->testingSqlitePath}testing.sqlite:books-first");
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesstore",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:stores",
    ];
    $newStores = Store::factory()->count(2)->create();
    $result = Book::find($bookId)
        ->stores;

    Book::find($bookId)
        ->stores()
        ->attach($newStores[0]->id);
    Book::find($bookId)
        ->stores()
        ->sync($newStores->pluck('id'));
    $cachedResult = $this
        ->cache()
        ->tags($tags)
        ->get($key)['value']
        ?? null;

    expect(array_diff(
        Book::find($bookId)->stores()->pluck((new Store)->getTable() . '.id')->toArray(),
        $newStores->pluck('id')->toArray()
    ))->toBeEmpty();
    expect($result)->not->toBeEmpty();
    expect($cachedResult)->toBeNull();
});
