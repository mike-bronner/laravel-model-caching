<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;
use Ramsey\Uuid\Uuid;

// The key format joins its segments with "-" and "_". A value containing either
// used to be written into the key raw, so it could spell out a different query's
// clauses and read that query's cached rows.

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

// "%" is the escape character, so it has to be escaped too, or the encoding
// is not reversible: the value "a%2Db" and the value "a-b" would both come
// out as "a%2Db" and share a key.
test('percent is escaped so the encoding stays reversible', function () {
    $literal = (new Book)->where("title", "a%2Db");
    $separator = (new Book)->where("title", "a-b");

    expect(cacheKey($literal))->toEqual(keyPrefix() . "-title_=_a%252Db");
    expect(cacheKey($separator))->toEqual(keyPrefix() . "-title_=_a%2Db");
    expect(cacheKey($separator))->not->toEqual(cacheKey($literal));
});

// Nothing else here would fail if the encoding were rewritten to replace
// its pairs sequentially with "%" reached last, which would encode the "%"
// of every "%2D" it had just written and turn "-" into "%252D". This pins
// the output against that. Verified by mutation: swapping strtr() for such
// a loop reddens this test, and reordering the pairs is what does it.
test('separator encoding is not double encoded', function () {
    expect(cacheKey((new Book)->where("title", "a-b_c%d")))->toEqual(
        keyPrefix() . "-title_=_a%2Db%5Fc%25d",
    );
});

// The escaping only rewrites the three characters above, so a value holding
// none of them keeps the exact bytes it had before — no existing cache entry
// for an ordinary value goes cold on upgrade.
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

// "_" joins the values of an "in" clause, so one value containing "_" spelt
// out two values and shared their key.
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

// The third return path of getInAndNotInClauses(), reached when a value
// contains "?": that path treats the "?" as a placeholder and rebuilds the
// clause by substituting bindings for it. Both the value and the
// substituted binding land in the key, so both are escaped.
//
// The repeated fragment in the expected key is that substitution folding
// the value back into itself. It is pre-existing behaviour of this path,
// not something the escaping introduced; it is pinned here rather than
// corrected, because rewriting that path is not what this change is for.
test('where in values containing a placeholder are escaped', function () {
    $key = cacheKey((new Book)->whereIn("title", ["a_b?c"]));

    expect($key)->toEqual(keyPrefix() . "-title_in_a%5Fba%5Fb?cc");
    expect($key)->not->toContain("_b?c");
});

// The binary-UUID branch of getInAndNotInClauses() recognises its value by
// being exactly 16 bytes long. Escaping runs below that branch precisely so
// a 0x2D or 0x5F byte inside a UUID cannot stretch it past 16 and send it
// down the wrong path. This UUID contains both bytes.
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
