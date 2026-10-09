<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;

/**
 * inRandomOrder() sets $this->isCachable = false on CachedBuilder, so
 * each call goes to the database instead of the cache. Verify the query
 * executes and returns a valid model instance.
 *
 * The previous assertion assertNotEquals($result1, $result2) was
 * probabilistically flaky: with a random seed, the same row can be
 * returned on consecutive calls.
 */
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
