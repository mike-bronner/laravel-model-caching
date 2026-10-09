<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;

test('destroy with non existent ids does not flush cache', function () {
    $key = populateBookCache();

    $result = Book::destroy([999998, 999999]);

    expect($result)->toEqual(0);
    expect($this->cache()->tags(bookTags())->get($key))->not->toBeNull();
});

test('destroy with existing id flushes cache', function () {
    $key = populateBookCache();
    $book = (new Book)->first();

    $result = Book::destroy($book->id);

    expect($result)->toEqual(1);
    expect($this->cache()->tags(bookTags())->get($key))->toBeNull();
});

test('destroy with multiple existing ids flushes cache', function () {
    $key = populateBookCache();
    $bookIds = (new Book)->take(3)->pluck('id')->toArray();

    $result = Book::destroy($bookIds);

    expect($result)->toEqual(3);
    expect($this->cache()->tags(bookTags())->get($key))->toBeNull();
});
