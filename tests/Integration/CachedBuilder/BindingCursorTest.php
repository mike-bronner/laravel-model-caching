<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;
use Illuminate\Database\Query\Expression;

// CacheKey walks the "where" binding array with a cursor, and every clause
// handler advances it by the number of bindings that clause consumed. Where
// the advance disagreed with what Laravel actually bound, every later clause
// read the wrong slot and two queries differing only in those later clauses
// built one key.
//
// Each test below pins the *following* clause's value in the key, because that
// is what a mis-advanced cursor corrupts. Asserting only on the In clause
// itself would stay green with the cursor still wrong.

// whereIntegerInRaw() inlines its values into the SQL and calls
// addBinding() not at all, so it owns no binding slot. Advancing by the
// value count pushed the cursor past the clauses that follow.
//
// Four trailing clauses, not two. getValuesFromBindings() falls back to
// the clause's literal value once the cursor runs off the end of the
// binding array, which produces the correct key by accident and hides the
// over-advance. The cursor has to land on a real binding belonging to a
// different clause for the defect to show.
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

// NotInRaw was named by neither getInAndNotInClauses() nor the exclusion
// list in getOtherClauses(), so it fell through to the generic
// single-binding path: its excluded values never reached the key at all,
// and it ate the next clause's binding on the way past.
test('where integer not in raw keys its excluded values', function () {
    $firstSet = cacheKey((new Book)->whereIntegerNotInRaw("id", [1, 2, 3])->where("title", "a"));
    $secondSet = cacheKey((new Book)->whereIntegerNotInRaw("id", [4, 5, 6])->where("title", "a"));

    expect($firstSet)->toEndWith("-id_notinraw_1_2_3-title_=_a");
    expect($secondSet)->toEndWith("-id_notinraw_4_5_6-title_=_a");
});

// Same fallback masking as the InRaw case above: four trailing clauses so
// the over-advanced cursor lands on a real binding rather than off the end.
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

// The binary-UUID branch returns as soon as it recognises a 16-byte value,
// and used to return without advancing at all, although whereIn() had
// bound that value.
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

// cleanBindings() drops an Expression, so whereIn() binds one value here,
// not two. This is the case PR #618 fixed; it is pinned again because the
// count moved into a shared helper that the *Raw types also use.
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
