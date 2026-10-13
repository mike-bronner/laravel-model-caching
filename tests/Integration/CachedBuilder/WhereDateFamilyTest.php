<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;

test('plain where key is unchanged', function () {
    expect(cacheKey((new Book)->where("published_at", "=", "2020-01-05")))->toEqual(
        keyPrefix() . "-published_at_=_2020%2D01%2D05",
    );
    expect(cacheKey((new Book)->where("id", "=", 5)))->toEqual(keyPrefix() . "-id_=_5");
    expect(cacheKey((new Book)->where("title", "like", "abc")))->toEqual(
        keyPrefix() . "-title_like_abc",
    );
});

test('where date produces a different cache key than where', function () {
    expect(cacheKey((new Book)->where("published_at", "=", "2020-01-05")))->toEqual(
        keyPrefix() . "-published_at_=_2020%2D01%2D05",
    );
    expect(cacheKey((new Book)->whereDate("published_at", "=", "2020-01-05")))->toEqual(
        keyPrefix() . "-published_at_date_=_2020%2D01%2D05",
    );
});

test('where day produces a different cache key than where month', function () {
    expect(cacheKey((new Book)->whereDay("published_at", 5)))->toEqual(
        keyPrefix() . "-published_at_day_=_05",
    );
    expect(cacheKey((new Book)->whereMonth("published_at", 5)))->toEqual(
        keyPrefix() . "-published_at_month_=_05",
    );
});

test('where year where time and where produce three different cache keys', function () {
    $keys = [
        cacheKey((new Book)->where("published_at", "=", 5)),
        cacheKey((new Book)->whereYear("published_at", 5)),
        cacheKey((new Book)->whereTime("published_at", "=", 5)),
    ];

    expect($keys[0])->toEqual(keyPrefix() . "-published_at_=_5");
    expect($keys[1])->toEqual(keyPrefix() . "-published_at_year_=_5");
    expect($keys[2])->toEqual(keyPrefix() . "-published_at_time_=_5");
    expect(array_unique($keys))->toHaveCount(3);
});

test('where date returns its own results after where was cached', function () {
    $book = (new UncachedBook)->first();
    $book->published_at = "2020-01-05 13:45:00";
    $book->save();

    assertFirstQueryWasCached(
        (new Book)->where("published_at", "=", "2020-01-05"),
        "The plain where-query"
    );

    $results = (new Book)
        ->whereDate("published_at", "=", "2020-01-05")
        ->get();
    $liveResults = (new UncachedBook)
        ->whereDate("published_at", "=", "2020-01-05")
        ->get();

    expect($results)->toHaveCount(1);
    expect($results->pluck("id"))->toEqual($liveResults->pluck("id"));
});

test('where month returns its own results after where day was cached', function () {
    $book = (new UncachedBook)->first();
    $book->published_at = "2020-01-05 13:45:00";
    $book->save();

    assertFirstQueryWasCached(
        (new Book)->whereDay("published_at", 5),
        "The whereDay-query"
    );

    $results = (new Book)->whereMonth("published_at", 5)->get();
    $liveResults = (new UncachedBook)->whereMonth("published_at", 5)->get();
    $dayResults = (new UncachedBook)->whereDay("published_at", 5)->get();

    expect($results->pluck("id"))->toEqual($liveResults->pluck("id"));
    expect($results->pluck("id"))->not->toEqual(
        $dayResults->pluck("id"),
        "The fixture must make the two queries return different rows, or this proves nothing",
    );
});

test('where time returns its own results after where year was cached', function () {
    $book = (new UncachedBook)->first();
    $book->published_at = "2020-01-05 13:45:00";
    $book->save();

    $yearQuery = (new Book)->whereYear("published_at", 2020);
    $timeQuery = (new Book)->whereTime("published_at", "=", 2020);

    expect(cacheKey($timeQuery))->not->toEqual(
        cacheKey($yearQuery),
        "Both bind 2020, so only the clause type can tell these keys apart",
    );

    assertFirstQueryWasCached($yearQuery, "The whereYear-query");

    $results = $timeQuery->get();
    $liveResults = (new UncachedBook)
        ->whereTime("published_at", "=", 2020)
        ->get();
    $yearResults = (new UncachedBook)
        ->whereYear("published_at", 2020)
        ->get();

    expect($yearResults)->not->toBeEmpty(
        "The fixture must make whereYear match something, or this proves nothing",
    );
    expect($results)->toBeEmpty();
    expect($results->pluck("id"))->toEqual($liveResults->pluck("id"));
});

test('where json length produces a different cache key than where', function () {
    expect(cacheKey((new Book)->where("description", ">", 1)))->toEqual(
        keyPrefix() . "-description_>_1",
    );
    expect(cacheKey((new Book)->whereJsonLength("description", ">", 1)))->toEqual(
        keyPrefix() . "-description_jsonlength_>_1",
    );
});

test('where sub produces a different cache key than where', function () {
    $plain = cacheKey((new Book)->where("author_id", "=", 1));
    $sub = cacheKey(
        (new Book)->where("author_id", "=", function ($query) {
            $query->from("authors")
                ->selectRaw("1");
        })
    );

    expect($plain)->toEqual(keyPrefix() . "-author_id_=_1");
    expect($sub)->toContain("-author_id_sub_=_");
    expect($sub)->not->toEqual($plain);
});
