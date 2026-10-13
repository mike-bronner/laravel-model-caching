<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;

test('from sub query is cached', function () {
    $results = (new Author)
        ->fromSub(
            fn ($query) => $query->from("authors")->where("id", ">", 1),
            "authors",
        )
        ->get();
    $liveResults = (new UncachedAuthor)
        ->fromSub(
            fn ($query) => $query->from("authors")->where("id", ">", 1),
            "authors",
        )
        ->get();

    expect($results)->not->toBeEmpty();
    expect($results->pluck("id"))->toEqual($liveResults->pluck("id"));
});

test('two different from sub queries do not share a key', function () {
    $first = (new Author)
        ->fromSub(
            fn ($query) => $query->from("authors")->where("id", ">", 1),
            "authors",
        );
    $second = (new Author)
        ->fromSub(
            fn ($query) => $query->from("authors")->where("id", ">", 2),
            "authors",
        );

    expect(cacheKey($first))->not->toBe(cacheKey($second));

    $liveResults = (new UncachedAuthor)
        ->fromSub(
            fn ($query) => $query->from("authors")->where("id", ">", 2),
            "authors",
        )
        ->get();

    $first->get();

    expect($second->get()->pluck("id"))->toEqual($liveResults->pluck("id"));
});

test('from sub query is tagged with the table its subquery reads', function () {
    assertCachedUnderTags(
        (new Book)->fromSub(fn ($query) => $query->from("authors")->select("id"), "books"),
        [...bookTags(), authorTags()[1]],
    );
});

test('from sub query over an eloquent builder is tagged with the table it reads', function () {
    assertCachedUnderTags(
        (new Book)->fromSub((new Author)->select("id"), "books"),
        [...bookTags(), authorTags()[1]],
    );
});

test('from sub query returns fresh rows after the table its subquery reads is written', function () {
    $query = fn () => (new Book)
        ->fromSub(fn ($query) => $query->from("authors")->select("id"), "books")
        ->get()
        ->pluck("id");

    expect($query())->not->toBeEmpty();

    $author = Author::factory()->create();

    expect($query())->toContain($author->id);
});
