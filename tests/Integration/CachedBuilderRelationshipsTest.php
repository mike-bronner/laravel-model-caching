<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;

test('has relationship results', function () {
    $booksWithStores = (new Book)
        ->with("stores")
        ->has("stores")
        ->get();
    $key = "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books:genealabslaravelmodelcachingtestsfixturesbook-exists-books.id_=_book_store.book_id-testing:{$this->testingSqlitePath}testing.sqlite:stores";
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesstore",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:stores",
    ];
    $cachedResults = $this
        ->cache()
        ->tags($tags)
        ->get(sha1($key))["value"];

    expect($booksWithStores)->not->toBeEmpty();
    expect($cachedResults)->toEqual($booksWithStores);
});

test('where has relationship', function () {
    $books = (new Book)
        ->with("stores")
        ->whereHas("stores", function ($query) {
            $query->whereRaw('address like ?', ['%s%']);
        })
        ->get();

    $uncachedBooks = (new UncachedBook)
        ->with("stores")
        ->whereHas("stores", function ($query) {
            $query->whereRaw('address like ?', ['%s%']);
        })
        ->get();

    expect($uncachedBooks->pluck("id"))->toEqual($books->pluck("id"));
});
