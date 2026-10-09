<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;

// getHavingClause() passed every member of the clause array to str_replace(),
// which only accepts a string or an array of them. having() stores an int or
// a float in "value", havingBetween() stores an array in "values", and both
// store a bool in "not". So having() raised a TypeError on a cold query, and
// havingBetween() folded both bounds into the literal "Array", giving every
// range one key.
//
// Book carries no global scope, so nothing but the clause under test moves
// these keys.

test('having with numeric value does not throw', function () {
    $books = (new Book)
        ->groupBy("author_id")
        ->having("author_id", ">", 1)
        ->get();
    $liveResults = (new UncachedBook)
        ->groupBy("author_id")
        ->having("author_id", ">", 1)
        ->get();

    expect($books->pluck("id"))->toEqual($liveResults->pluck("id"));
    expect($books)->not->toBeEmpty();
});

test('having between does not throw', function () {
    $books = (new Book)
        ->groupBy("author_id")
        ->havingBetween("author_id", [1, 3])
        ->get();
    $liveResults = (new UncachedBook)
        ->groupBy("author_id")
        ->havingBetween("author_id", [1, 3])
        ->get();

    expect($books->pluck("id"))->toEqual($liveResults->pluck("id"));
    expect($books)->not->toBeEmpty();
});

test('having keys the bound value rather than its type', function () {
    $lowBound = cacheKey((new Book)->groupBy("author_id")->having("author_id", ">", 1));
    $highBound = cacheKey((new Book)->groupBy("author_id")->having("author_id", ">", 3));

    expect($lowBound)->toContain(
        "-having_type_Basic_column_author_id_operator_>_value_1_boolean_and",
    );
    expect($highBound)->toContain(
        "-having_type_Basic_column_author_id_operator_>_value_3_boolean_and",
    );
});

test('having between keys both bounds rather than the word array', function () {
    $narrow = cacheKey((new Book)->groupBy("author_id")->havingBetween("author_id", [1, 3]));
    $wide = cacheKey((new Book)->groupBy("author_id")->havingBetween("author_id", [4, 9]));

    expect($narrow)->toContain("_values_1_3_");
    expect($wide)->toContain("_values_4_9_");
    expect($narrow)->not->toContain("Array");
});

test('having between returns its own rows after another range was cached', function () {
    (new Book)->groupBy("author_id")->havingBetween("author_id", [1, 2])->get();

    $books = (new Book)->groupBy("author_id")->havingBetween("author_id", [3, 4])->get();
    $liveResults = (new UncachedBook)->groupBy("author_id")->havingBetween("author_id", [3, 4])->get();

    expect($books->pluck("id"))->toEqual($liveResults->pluck("id"));
});

// havingRaw() keeps its bindings out of the clause array, so two calls
// differing only in a bound value produce the same clause string. The
// "having" binding channel is what tells them apart.
test('having raw keys its bindings', function () {
    $lowBound = cacheKey((new Book)->groupBy("author_id")->havingRaw("author_id > ?", [1]));
    $highBound = cacheKey((new Book)->groupBy("author_id")->havingRaw("author_id > ?", [3]));

    expect($highBound)->not->toEqual($lowBound);
    expect($lowBound)->toContain("-havingBindings_1");
    expect($highBound)->toContain("-havingBindings_3");
});

test('having raw returns its own rows after another binding was cached', function () {
    (new Book)->groupBy("author_id")->havingRaw("author_id > ?", [1])->get();

    $books = (new Book)->groupBy("author_id")->havingRaw("author_id > ?", [3])->get();
    $liveResults = (new UncachedBook)->groupBy("author_id")->havingRaw("author_id > ?", [3])->get();

    expect($books->pluck("id"))->toEqual($liveResults->pluck("id"));
});

// A raw having with no bindings is the one shape that already worked, so
// its key has to stay exactly as it was or every cached one goes cold.
test('unbound having raw key is unchanged', function () {
    $key = cacheKey((new Book)->groupBy("author_id")->havingRaw("count(*) > 1"));

    expect($key)->toEndWith("-groupBy_author_id-having_type_Raw_sql_count(*)_>_1_boolean_and");
});

test('or having keys distinctly from having', function () {
    $conjunction = cacheKey((new Book)
        ->groupBy("author_id")
        ->having("author_id", ">", 1)
        ->having("author_id", "<", 9));
    $disjunction = cacheKey((new Book)
        ->groupBy("author_id")
        ->having("author_id", ">", 1)
        ->orHaving("author_id", "<", 9));

    expect($disjunction)->not->toEqual($conjunction);
    expect($disjunction)->toContain("_boolean_or");
});
