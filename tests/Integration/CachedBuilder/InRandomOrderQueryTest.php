<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;

test('in random order caches results', function () {
    $cachedBook1 = (new Book)
        ->inRandomOrder()
        ->first();
    $cachedBook2 = (new Book)
        ->inRandomOrder()
        ->first();
    $book1 = (new UncachedBook)
        ->inRandomOrder()
        ->first();
    $book2 = (new UncachedBook)
        ->inRandomOrder()
        ->first();

    expect($cachedBook1)->not->toBeNull();
    expect($cachedBook2)->not->toBeNull();
    expect($cachedBook1)->toBeInstanceOf(Book::class);
    expect($cachedBook2)->toBeInstanceOf(Book::class);
    expect($book1)->toBeInstanceOf(UncachedBook::class);
    expect($book2)->toBeInstanceOf(UncachedBook::class);
});
