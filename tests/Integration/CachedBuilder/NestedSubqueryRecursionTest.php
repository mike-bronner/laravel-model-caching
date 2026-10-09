<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;

// A subquery carrying no where clause used to make getNestedClauses() call
// getWhereClauses([]), which getWheres() read as "no argument given" and
// answered with the outer query's clauses. Those still hold the clause that
// asked, so the walk had no floor and the process died on SIGSEGV.
//
// A segfault is not an exception, so these tests cannot assert on one. They
// assert the query returns its rows instead: without the fix the PHP process
// never reaches the assertion and Pest reports the crash.

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

// The empty subquery has to key as empty rather than as a copy of the
// outer clauses. Asserting only that the query completes would stay green
// if the recursion were replaced by a depth cap, which would still write
// the outer clauses into the nested segment.
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
