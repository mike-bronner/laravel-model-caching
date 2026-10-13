<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;

test('where exists without inner where clauses builds a key', function () {
    $books = (new Book)
        ->whereExists(fn ($query) => $query->select("id")->from("authors"))
        ->get();
    $liveResults = (new UncachedBook)
        ->whereExists(fn ($query) => $query->select("id")->from("authors"))
        ->get();

    expect($books->pluck("id"))->toEqual($liveResults->pluck("id"));
    expect($books)->not->toBeEmpty();
});

test('where not exists without inner where clauses builds a key', function () {
    $books = (new Book)
        ->whereNotExists(fn ($query) => $query->select("id")->from("authors"))
        ->get();
    $liveResults = (new UncachedBook)
        ->whereNotExists(fn ($query) => $query->select("id")->from("authors"))
        ->get();

    expect($books->pluck("id"))->toEqual($liveResults->pluck("id"));
});

test('empty nested subquery contributes no clauses to the key', function () {
    $key = cacheKey((new Book)
        ->where("title", "a")
        ->whereExists(fn ($query) => $query->select("id")->from("authors")));

    expect($key)->toEndWith("-title_=_a-exists");
});

test('constrained nested subquery still contributes its own clauses', function () {
    $key = cacheKey((new Book)
        ->where("title", "a")
        ->whereExists(fn ($query) => $query->select("id")->from("authors")->where("id", 1)));

    expect($key)->toEndWith("-title_=_a-exists-id_=_1");
});

test('where exists still returns its own rows after where not exists was cached', function () {
    (new Book)
        ->whereNotExists(fn ($query) => $query->select("id")->from("authors"))
        ->get();

    $books = (new Book)
        ->whereExists(fn ($query) => $query->select("id")->from("authors"))
        ->get();
    $liveResults = (new UncachedBook)
        ->whereExists(fn ($query) => $query->select("id")->from("authors"))
        ->get();

    expect($books->pluck("id"))->toEqual($liveResults->pluck("id"));
});
