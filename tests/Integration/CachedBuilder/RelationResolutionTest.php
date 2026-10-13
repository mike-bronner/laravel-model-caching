<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\BookWithFakeRelation;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\CountingBook;

test('has resolves the relation once', function () {
    expect(tally(
        fn () => CountingBook::has("author")->get(),
    ))->toEqual(1);
});

test('count constrained has resolves the relation once', function () {
    expect(tally(
        fn () => CountingBook::has("author", ">", 0)->get(),
    ))->toEqual(1);
});

test('doesnt have resolves the relation once', function () {
    expect(tally(
        fn () => CountingBook::doesntHave("author")->get(),
    ))->toEqual(1);
});

test('where has resolves the relation once', function () {
    expect(tally(
        fn () => CountingBook::whereHas("author", fn ($query) => $query->where("id", ">", 0))->get(),
    ))->toEqual(1);
});

test('with count resolves the relation once', function () {
    expect(tally(
        fn () => CountingBook::withCount("author")->get(),
    ))->toEqual(1);
});

test('with exists resolves the relation once', function () {
    expect(tally(
        fn () => CountingBook::withExists("author")->get(),
    ))->toEqual(1);
});

test('aliased with count resolves the relation once', function () {
    expect(tally(
        fn () => CountingBook::withCount("author as writer_count")->get(),
    ))->toEqual(1);
});

test('constrained with count resolves the relation once', function () {
    expect(tally(
        fn () => CountingBook::withCount([
            "author" => fn ($query) => $query->where("id", ">", 0),
        ])->get(),
    ))->toEqual(1);
});

test('a non relation return value fails in laravel rather than here', function () {
    $exception = null;

    try {
        (new BookWithFakeRelation)->withCount("notReallyARelation")->get();
    } catch (Throwable $thrown) {
        $exception = $thrown;
    }

    expect($exception)->toBeInstanceOf(Throwable::class);
    expect($exception->getFile())->not->toContain(
        dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . "src",
    );
});

test('unknown segment ends the walk and leaves the error to laravel', function () {
    try {
        tally(fn () => CountingBook::has("author.notARelation")->get());

        $this->fail("Expected a BadMethodCallException.");
    } catch (BadMethodCallException $exception) {
        expect(CountingBook::$authorCalls)->toEqual(2);
    }
});

test('dotted chain resolves the first segment twice', function () {
    expect(tally(
        fn () => CountingBook::has("author.books")->get(),
    ))->toEqual(2);
});

function tally(callable $query): int
{
    CountingBook::$authorCalls = 0;
    $query();

    return CountingBook::$authorCalls;
}
