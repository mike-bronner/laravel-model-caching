<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;
use Illuminate\Database\Query\Builder;

test('upsert invalidates cache', function () {
    assertCachedTitlesMatchDatabase(function () {
        $row = ["id" => 1] + newBookRow("upserted");

        Book::upsert([$row], ["id"], ["title"]);
    });
});

test('insert or ignore invalidates cache', function () {
    assertCachedTitlesMatchDatabase(function () {
        Book::insertOrIgnore([newBookRow("inserted or ignored")]);
    });
});

test('fill and insert or ignore invalidates cache', function () {
    assertCachedTitlesMatchDatabase(function () {
        Book::fillAndInsertOrIgnore([newBookRow("filled and inserted")]);
    });
});

test('insert or ignore returning invalidates cache', function () {
    assertCachedTitlesMatchDatabase(function () {
        Book::insertOrIgnoreReturning([newBookRow("inserted returning")], ["id"]);
    });
})->skip(
    ! method_exists(Builder::class, "insertOrIgnoreReturning"),
    "Method " . Builder::class . "::insertOrIgnoreReturning() does not exist",
);

test('insert get id invalidates cache', function () {
    assertCachedTitlesMatchDatabase(function () {
        Book::insertGetId(newBookRow("inserted with id"));
    });
});

test('insert using invalidates cache', function () {
    assertCachedTitlesMatchDatabase(function () {
        Book::insertUsing(
            ["author_id", "publisher_id", "published_at", "title", "price"],
            (new UncachedBook)
                ->selectRaw("author_id, publisher_id, published_at, 'copied', price")
                ->where("id", 1),
        );
    });
});

test('insert or ignore using invalidates cache', function () {
    assertCachedTitlesMatchDatabase(function () {
        Book::insertOrIgnoreUsing(
            ["author_id", "publisher_id", "published_at", "title", "price"],
            (new UncachedBook)
                ->selectRaw("author_id, publisher_id, published_at, 'copied or ignored', price")
                ->where("id", 1),
        );
    });
});

test('update or insert invalidates cache', function () {
    assertCachedTitlesMatchDatabase(function () {
        Book::updateOrInsert(["id" => 1], ["title" => "updated or inserted"]);
    });
});

// Creating a model runs insertGetId() underneath. With model events off,
// as under saveQuietly(), that write is the only thing left to invalidate.
test('quiet create invalidates cache', function () {
    assertCachedTitlesMatchDatabase(function () {
        (new Book)->fill(newBookRow("created quietly"))->saveQuietly();
    });
});

test('touch invalidates cache', function () {
    $before = (new Book)->findOrFail(1)->updated_at;

    $this->travel(1)->hours();
    (new Book)->where("id", 1)->touch();

    $after = (new Book)->findOrFail(1)->updated_at;
    $live = (new UncachedBook)->findOrFail(1)->updated_at;

    expect($live)->not->toEqual($before);
    expect($after)->toEqual($live);
});

test('increment each invalidates cache', function () {
    $before = (new Book)->findOrFail(1)->price;

    (new Book)->where("id", 1)->incrementEach(["price" => 2]);

    expect((new Book)->findOrFail(1)->price)->toEqual($before + 2);
});

test('decrement each invalidates cache', function () {
    $before = (new Book)->findOrFail(1)->price;

    (new Book)->where("id", 1)->decrementEach(["price" => 2]);

    expect((new Book)->findOrFail(1)->price)->toEqual($before - 2);
});

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
