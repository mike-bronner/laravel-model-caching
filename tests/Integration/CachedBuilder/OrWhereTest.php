<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;

// An "or" clause widens a result set; its "and" twin narrows it. They used to
// share a cache key in every clause family except Basic, so the cheaper query
// answered for the stricter one.
//
// Book carries no global scope on purpose. Author uses SoftDeletes, and
// Eloquent's addNewWheresWithinGroup() wraps the pre-existing clauses in a
// nested group the moment an "or" appears, which moves the key on its own — a
// test written against Author cannot tell that apart from the boolean landing
// in the key.

test('or where produces different cache key than where', function () {
    $and = (new Book)->where("id", 1)->where("id", 2);
    $or = (new Book)->where("id", 1)->orWhere("id", 2);

    expect(cacheKey($and))->toEqual(keyPrefix() . "-id_=_1-id_=_2");
    expect(cacheKey($or))->toEqual(keyPrefix() . "-id_=_1-or-id_=_2");
});

test('or where returns its own results after where was cached', function () {
    assertFirstQueryWasCached(
        (new Book)->where("id", 1)->where("id", 2),
        "The and-query"
    );

    $results = (new Book)->where("id", 1)->orWhere("id", 2)->get();
    $liveResults = (new UncachedBook)->where("id", 1)->orWhere("id", 2)->get();

    expect($results->pluck("id"))->toEqual($liveResults->pluck("id"));
    expect($results)->toHaveCount(2);
});

test('or where closure produces different cache key than where closure', function () {
    $and = (new Book)
        ->where("id", 1)
        ->where(function ($query) {
            $query->where("id", 2);
        });
    $or = (new Book)
        ->where("id", 1)
        ->orWhere(function ($query) {
            $query->where("id", 2);
        });

    expect(cacheKey($and))->toEqual(keyPrefix() . "-id_=_1-nested-id_=_2");
    expect(cacheKey($or))->toEqual(keyPrefix() . "-id_=_1-or-nested-id_=_2");
});

test('or where closure returns its own results after where closure was cached', function () {
    assertFirstQueryWasCached(
        (new Book)
            ->where("id", 1)
            ->where(function ($query) {
                $query->where("id", 2);
            }),
        "The and-query"
    );

    $results = (new Book)
        ->where("id", 1)
        ->orWhere(function ($query) {
            $query->where("id", 2);
        })
        ->get();
    $liveResults = (new UncachedBook)
        ->where("id", 1)
        ->orWhere(function ($query) {
            $query->where("id", 2);
        })
        ->get();

    expect($results->pluck("id"))->toEqual($liveResults->pluck("id"));
    expect($results)->toHaveCount(2);
});

test('or where has produces different cache key than where has', function () {
    $and = (new Book)
        ->where("id", 1)
        ->whereHas("author", function ($query) {
            $query->where("id", 2);
        });
    $or = (new Book)
        ->where("id", 1)
        ->orWhereHas("author", function ($query) {
            $query->where("id", 2);
        });
    $existsClause = "-exists-books.author_id_=_authors.id-id_=_2-authors.deleted_at_null";

    expect(cacheKey($and))->toEqual(keyPrefix() . "-id_=_1" . $existsClause);
    expect(cacheKey($or))->toEqual(keyPrefix() . "-id_=_1-or" . $existsClause);
});

test('or where has returns its own results after where has was cached', function () {
    assertFirstQueryWasCached(
        (new Book)
            ->where("id", 1)
            ->whereHas("author", function ($query) {
                $query->where("id", 2);
            }),
        "The whereHas-query",
        ["genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:authors"]
    );

    $results = (new Book)
        ->where("id", 1)
        ->orWhereHas("author", function ($query) {
            $query->where("id", 2);
        })
        ->get();
    $liveResults = (new UncachedBook)
        ->where("id", 1)
        ->orWhereHas("author", function ($query) {
            $query->where("id", 2);
        })
        ->get();

    expect($results)->not->toBeEmpty();
    expect($results->pluck("id"))->toEqual($liveResults->pluck("id"));
});

test('or where in produces different cache key than where in', function () {
    $and = (new Book)->where("id", 1)->whereIn("id", [1, 2]);
    $or = (new Book)->where("id", 1)->orWhereIn("id", [1, 2]);

    expect(cacheKey($and))->toEqual(keyPrefix() . "-id_=_1-id_in_1_2");
    expect(cacheKey($or))->toEqual(keyPrefix() . "-id_=_1-or-id_in_1_2");
});

test('or where in returns its own results after where in was cached', function () {
    assertFirstQueryWasCached(
        (new Book)->where("id", 1)->whereIn("id", [1, 2]),
        "The whereIn-query"
    );

    $results = (new Book)->where("id", 1)->orWhereIn("id", [1, 2])->get();
    $liveResults = (new UncachedBook)->where("id", 1)->orWhereIn("id", [1, 2])->get();

    expect($results->pluck("id"))->toEqual($liveResults->pluck("id"));
    expect($results)->toHaveCount(2);
});

test('or where not in produces different cache key than where not in', function () {
    $and = (new Book)->where("id", 1)->whereNotIn("id", [1, 2]);
    $or = (new Book)->where("id", 1)->orWhereNotIn("id", [1, 2]);

    expect(cacheKey($and))->toEqual(keyPrefix() . "-id_=_1-id_notin_1_2");
    expect(cacheKey($or))->toEqual(keyPrefix() . "-id_=_1-or-id_notin_1_2");
});

test('or where not in returns its own results after where not in was cached', function () {
    assertFirstQueryWasCached(
        (new Book)->where("id", 1)->whereNotIn("id", [1, 2]),
        "The whereNotIn-query"
    );

    $results = (new Book)->where("id", 1)->orWhereNotIn("id", [1, 2])->get();
    $liveResults = (new UncachedBook)->where("id", 1)->orWhereNotIn("id", [1, 2])->get();

    expect($results)->not->toBeEmpty();
    expect($results->pluck("id"))->toEqual($liveResults->pluck("id"));
});

test('or where integer in raw produces different cache key than where integer in raw', function () {
    $and = (new Book)->where("id", 1)->whereIntegerInRaw("id", [1, 2]);
    $or = (new Book)->where("id", 1)->orWhereIntegerInRaw("id", [1, 2]);

    expect(cacheKey($and))->toEqual(keyPrefix() . "-id_=_1-id_inraw_1_2");
    expect(cacheKey($or))->toEqual(keyPrefix() . "-id_=_1-or-id_inraw_1_2");
});

test('or where integer in raw returns its own results after where integer in raw was cached', function () {
    assertFirstQueryWasCached(
        (new Book)->where("id", 1)->whereIntegerInRaw("id", [1, 2]),
        "The whereIntegerInRaw-query"
    );

    $results = (new Book)->where("id", 1)->orWhereIntegerInRaw("id", [1, 2])->get();
    $liveResults = (new UncachedBook)->where("id", 1)->orWhereIntegerInRaw("id", [1, 2])->get();

    expect($results)->toHaveCount(2);
    expect($results->pluck("id"))->toEqual($liveResults->pluck("id"));
});
