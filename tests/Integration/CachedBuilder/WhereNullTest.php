<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;

test('where null clause', function () {
    $books = (new Book)
        ->whereNull("description")
        ->get();
    $uncachedBooks = (new UncachedBook)
        ->whereNull("description")
        ->get();

    expect($uncachedBooks->pluck("id"))->toEqual($books->pluck("id"));
});

test('nested where null clauses', function () {
    $books = (new Book)
        ->where(function ($query) {
            $query->whereNull("description");
        })
        ->get();
    $uncachedBooks = (new UncachedBook)
        ->where(function ($query) {
            $query->whereNull("description");
        })
        ->get();

    expect($uncachedBooks->pluck("id"))->toEqual($books->pluck("id"));
});
