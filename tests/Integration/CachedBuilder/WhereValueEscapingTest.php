<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;
use Ramsey\Uuid\Uuid;

test('value containing separators does not collide with two separate clauses', function () {
    $oneValue = (new Book)->where("title", "a-title_=_b");
    $twoClauses = (new Book)->where("title", "a")->where("title", "b");

    expect(cacheKey($oneValue))->toEqual(keyPrefix() . "-title_=_a%2Dtitle%5F=%5Fb");
    expect(cacheKey($twoClauses))->toEqual(keyPrefix() . "-title_=_a-title_=_b");
    expect(cacheKey($twoClauses))->not->toEqual(cacheKey($oneValue));
});

test('value containing separators returns its own results after the colliding query was cached', function () {
    $book = (new UncachedBook)->first();
    $book->title = "a-title_=_b";
    $book->save();

    $collidingQuery = (new Book)->where("title", "a")->where("title", "b");
    $collidingKey = sha1(cacheKey($collidingQuery));
    $collidingResults = $collidingQuery->get();
    $tags = [
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:genealabslaravelmodelcachingtestsfixturesbook",
        "genealabs:laravel-model-caching:testing:{$this->testingSqlitePath}testing.sqlite:books",
    ];

    $cached = $this->cache()
        ->tags($tags)
        ->get($collidingKey);

    expect($cached)->not->toBeNull(
        "The first query must populate the cache, or the second query has nothing to collide with",
    );
    expect($collidingResults)->toBeEmpty();

    $results = (new Book)->where("title", "a-title_=_b")->get();
    $liveResults = (new UncachedBook)->where("title", "a-title_=_b")->get();

    expect($results)->toHaveCount(1);
    expect($results->pluck("id"))->toEqual($liveResults->pluck("id"));
});

test('percent is escaped so the encoding stays reversible', function () {
    $literal = (new Book)->where("title", "a%2Db");
    $separator = (new Book)->where("title", "a-b");

    expect(cacheKey($literal))->toEqual(keyPrefix() . "-title_=_a%252Db");
    expect(cacheKey($separator))->toEqual(keyPrefix() . "-title_=_a%2Db");
    expect(cacheKey($separator))->not->toEqual(cacheKey($literal));
});

test('separator encoding is not double encoded', function () {
    expect(cacheKey((new Book)->where("title", "a-b_c%d")))->toEqual(
        keyPrefix() . "-title_=_a%2Db%5Fc%25d",
    );
});

test('keys for values without separators are unchanged', function () {
    $cases = [
        "a" => "-title_=_a",
        "plain title" => "-title_=_plain title",
        "Ünïcödé" => "-title_=_Ünïcödé",
        "0" => "-title_=_0",
        "a.b:c/d" => "-title_=_a.b:c/d",
    ];

    foreach ($cases as $value => $expectedClause) {
        expect(cacheKey((new Book)->where("title", (string) $value)))->toEqual(
            keyPrefix() . $expectedClause,
            "The key for the value {$value} must not change",
        );
    }
});

test('both between bounds are escaped', function () {
    $query = (new Book)->whereBetween("title", ["a-b", "c_d"]);

    expect(cacheKey($query))->toEqual(keyPrefix() . "-title_between_a%2Db_c%5Fd");
});

test('row values are escaped', function () {
    $query = (new Book)->whereRowValues(["title", "description"], "=", ["a-b", "c_d"]);

    expect(cacheKey($query))->toEqual(keyPrefix() . "-title_description_=_a%2Db_c%5Fd");
});

test('where in values are escaped', function () {
    $oneValue = (new Book)->whereIn("title", ["1_2"]);
    $twoValues = (new Book)->whereIn("title", ["1", "2"]);

    expect(cacheKey($oneValue))->toEqual(keyPrefix() . "-title_in_1%5F2");
    expect(cacheKey($twoValues))->toEqual(keyPrefix() . "-title_in_1_2");
    expect(cacheKey($twoValues))->not->toEqual(cacheKey($oneValue));
});

test('where not in values are escaped', function () {
    expect(cacheKey((new Book)->whereNotIn("title", ["a-b"])))->toEqual(
        keyPrefix() . "-title_notin_a%2Db",
    );
});

test('where in values containing a placeholder are escaped', function () {
    $key = cacheKey((new Book)->whereIn("title", ["a_b?c"]));

    expect($key)->toEqual(keyPrefix() . "-title_in_a%5Fba%5Fb?cc");
    expect($key)->not->toContain("_b?c");
});

test('binary uuid where in value is not escaped', function () {
    $uuid = Uuid::fromString("2d5f2d5f-2d5f-4d5f-8d5f-2d5f2d5f2d5f");
    $bytes = $uuid->getBytes();

    expect(strlen($bytes))->toBe(16);
    expect($bytes)->toContain("-");
    expect($bytes)->toContain("_");

    expect(cacheKey((new Book)->whereIn("id", [$bytes])))->toEqual(
        keyPrefix() . "-id_in_{$uuid->toString()}",
    );
});
