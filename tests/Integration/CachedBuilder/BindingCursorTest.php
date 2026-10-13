<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;
use Illuminate\Database\Query\Expression;

test('where integer in raw consumes no bindings', function () {
    $key = cacheKey((new Book)
        ->whereIntegerInRaw("id", [1, 2])
        ->where("title", "a")
        ->where("description", "b")
        ->where("title", "c")
        ->where("description", "d"));

    expect($key)->toEndWith("-id_inraw_1_2-title_=_a-description_=_b-title_=_c-description_=_d");
});

test('where integer in raw returns its own rows after another filter was cached', function () {
    (new Book)
        ->whereIntegerInRaw("id", [1, 2])
        ->where("title", "a")->where("description", "b")
        ->where("title", "c")->where("description", "d")
        ->get();

    $books = (new Book)
        ->whereIntegerInRaw("id", [1, 2])
        ->where("title", "e")->where("description", "f")
        ->where("title", "c")->where("description", "d")
        ->get();
    $liveResults = (new UncachedBook)
        ->whereIntegerInRaw("id", [1, 2])
        ->where("title", "e")->where("description", "f")
        ->where("title", "c")->where("description", "d")
        ->get();

    expect($books->pluck("id"))->toEqual($liveResults->pluck("id"));
});

test('where integer not in raw keys its excluded values', function () {
    $firstSet = cacheKey((new Book)->whereIntegerNotInRaw("id", [1, 2, 3])->where("title", "a"));
    $secondSet = cacheKey((new Book)->whereIntegerNotInRaw("id", [4, 5, 6])->where("title", "a"));

    expect($firstSet)->toEndWith("-id_notinraw_1_2_3-title_=_a");
    expect($secondSet)->toEndWith("-id_notinraw_4_5_6-title_=_a");
});

test('where integer not in raw consumes no bindings', function () {
    $key = cacheKey((new Book)
        ->whereIntegerNotInRaw("id", [1, 2])
        ->where("title", "a")
        ->where("description", "b")
        ->where("title", "c")
        ->where("description", "d"));

    expect($key)->toEndWith("-id_notinraw_1_2-title_=_a-description_=_b-title_=_c-description_=_d");
});

test('where integer not in raw returns its own rows after another exclusion was cached', function () {
    (new Book)->whereIntegerNotInRaw("id", [1, 2])->get();

    $books = (new Book)->whereIntegerNotInRaw("id", [3, 4])->get();
    $liveResults = (new UncachedBook)->whereIntegerNotInRaw("id", [3, 4])->get();

    expect($books->pluck("id"))->toEqual($liveResults->pluck("id"));
});

test('binary uuid where in advances the cursor before returning', function () {
    $uuid = hex2bin("0123456789abcdef0123456789abcdef");

    $key = cacheKey((new Book)
        ->whereIn("id", [$uuid])
        ->where("title", "a")
        ->where("description", "b"));

    expect($key)->toEndWith(
        "-id_in_01234567-89ab-cdef-0123-456789abcdef-title_=_a-description_=_b",
    );
});

test('where in with an expression value consumes only the bound values', function () {
    $key = cacheKey((new Book)
        ->whereIn("id", [1, new Expression("2")])
        ->where("title", "a")
        ->where("description", "b"));

    expect($key)->toEndWith("-title_=_a-description_=_b");
});

test('where row values with an expression consumes only the bound values', function () {
    $key = cacheKey((new Book)
        ->whereRowValues(["id", "title"], "=", [1, new Expression("'x'")])
        ->where("description", "b")
        ->where("title", "a"));

    expect($key)->toEndWith("-description_=_b-title_=_a");
});

test('where between with an expression bound consumes only one slot', function () {
    $key = cacheKey((new Book)
        ->whereBetween("id", [new Expression("1"), 10])
        ->where("description", "b")
        ->where("title", "a"));

    expect($key)->toEndWith("-description_=_b-title_=_a");
});

test('where between with two bound values still consumes both slots', function () {
    $key = cacheKey((new Book)
        ->whereBetween("id", [1, 10])
        ->where("title", "a"));

    expect($key)->toEndWith("-id_between_1_10-title_=_a");
});

test('basic where with an expression value consumes no binding', function () {
    $key = cacheKey((new Book)
        ->where("id", ">", new Expression("1"))
        ->where("description", "b")
        ->where("title", "a"));

    expect($key)->toEndWith("-description_=_b-title_=_a");
});
