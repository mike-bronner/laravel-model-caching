<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;

function newBookRow(string $title): array
{
    $book = (new UncachedBook)->findOrFail(1);

    return [
        "author_id" => $book->author_id,
        "publisher_id" => $book->publisher_id,
        "published_at" => $book->published_at,
        "title" => $title,
        "price" => 1,
    ];
}

function assertCachedTitlesMatchDatabase(callable $write): void
{
    $before = (new Book)->orderBy("id")->pluck("title");

    $write();

    $after = (new Book)->orderBy("id")->pluck("title");
    $live = (new UncachedBook)->orderBy("id")->pluck("title");

    expect($live)->not->toEqual($before, "The write must change what the database returns");
    expect($after)->toEqual($live);
}

function cachedTitles()
{
    return (new Book)->orderBy("id")->pluck("title");
}

function liveTitles()
{
    return (new UncachedBook)->orderBy("id")->pluck("title");
}
