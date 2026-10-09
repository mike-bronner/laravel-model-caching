<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\BookWithCooldown;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;

test('increment respects cooldown', function () {
    assertWriteKeepsCacheDuringCooldown(function () {
        (new BookWithCooldown)->where("id", 1)->increment("price", 5);
    });
});

test('model increment respects cooldown', function () {
    assertWriteKeepsCacheDuringCooldown(function () {
        (new BookWithCooldown)->newQuery()->findOrFail(1)->increment("price", 5);
    });
});

test('decrement respects cooldown', function () {
    assertWriteKeepsCacheDuringCooldown(function () {
        (new BookWithCooldown)->where("id", 1)->decrement("price", 5);
    });
});

test('delete respects cooldown', function () {
    assertWriteKeepsCacheDuringCooldown(function () {
        (new BookWithCooldown)->where("id", 1)->delete();
    });
});

test('force delete respects cooldown', function () {
    assertWriteKeepsCacheDuringCooldown(function () {
        (new BookWithCooldown)->where("id", 1)->forceDelete();
    });
});

test('truncate respects cooldown', function () {
    assertWriteKeepsCacheDuringCooldown(function () {
        (new BookWithCooldown)->newQuery()->truncate();
    });
});

test('destroy respects cooldown', function () {
    assertWriteKeepsCacheDuringCooldown(function () {
        BookWithCooldown::destroy(1);
    });
});

test('update respects cooldown', function () {
    assertWriteKeepsCacheDuringCooldown(function () {
        (new BookWithCooldown)->where("id", 1)->update(["price" => 12345]);
    });
});

test('upsert respects cooldown', function () {
    assertWriteKeepsCacheDuringCooldown(function () {
        $book = (new UncachedBook)->findOrFail(1);

        BookWithCooldown::upsert(
            [[
                "id" => 1,
                "author_id" => $book->author_id,
                "publisher_id" => $book->publisher_id,
                "published_at" => $book->published_at,
                "title" => $book->title,
                "price" => 12345,
            ]],
            ["id"],
            ["price"],
        );
    });
});

test('increment each respects cooldown', function () {
    assertWriteKeepsCacheDuringCooldown(function () {
        (new BookWithCooldown)->where("id", 1)->incrementEach(["price" => 5]);
    });
});

test('forwarded write respects cooldown', function () {
    assertWriteKeepsCacheDuringCooldown(function () {
        BookWithCooldown::updateOrInsert(["id" => 1], ["price" => 12345]);
    });
});

test('write after cooldown expires flushes', function () {
    startCooldown();
    cachedPrices();
    expect(booksTagEntrySets())->not->toBeEmpty();

    $this->travel(61)->seconds();
    (new BookWithCooldown)->where("id", 1)->increment("price", 5);

    expect(booksTagEntrySets())->toBeEmpty();
});

test('read after cooldown expires flushes writes made during it', function () {
    startCooldown();
    cachedPrices();
    (new BookWithCooldown)->where("id", 1)->increment("price", 5);

    $this->travel(61)->seconds();

    expect(cachedPrices())->toEqual(livePrices());
});

function startCooldown(): void
{
    (new BookWithCooldown)
        ->withCacheCooldownSeconds(60)
        ->get();
}

function cachedPrices(): array
{
    return (new BookWithCooldown)->orderBy("id")->pluck("price", "id")->all();
}

function livePrices(): array
{
    return (new UncachedBook)->orderBy("id")->pluck("price", "id")->all();
}

function assertWriteKeepsCacheDuringCooldown(callable $write): void
{
    startCooldown();
    $before = cachedPrices();

    $write();

    expect(livePrices())->not->toEqual($before, "The write must change what the database returns");
    expect(cachedPrices())->toEqual($before);
}
