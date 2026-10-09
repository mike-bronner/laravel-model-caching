<?php

use GeneaLabs\LaravelModelCaching\CacheKey;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;
use Illuminate\Support\Facades\DB;

// make() read the where clauses, the having clauses, the columns, the orders,
// the limit and the offset, and nothing else. groupBy, joins, unions and
// distinct never reached the key, so two queries differing only in one of them
// shared a key and the second read the first one's rows.
//
// Book carries no global scope, so nothing but the part under test moves these
// keys.

test('group by columns reach the key', function () {
    $byAuthor = cacheKey((new Book)->groupBy("author_id"));
    $byPublisher = cacheKey((new Book)->groupBy("publisher_id"));

    expect($byAuthor)->toEndWith("-groupBy_author_id");
    expect($byPublisher)->toEndWith("-groupBy_publisher_id");
});

test('group by returns its own rows after another grouping was cached', function () {
    (new Book)->groupBy("author_id")->get();

    $books = (new Book)->groupBy("publisher_id")->get();
    $liveResults = (new UncachedBook)->groupBy("publisher_id")->get();

    expect($books->pluck("id"))->toEqual($liveResults->pluck("id"));
});

test('group by raw keys its bindings', function () {
    $lowBound = cacheKey((new Book)->groupByRaw("author_id + ?", [1]));
    $highBound = cacheKey((new Book)->groupByRaw("author_id + ?", [2]));

    expect($highBound)->not->toEqual($lowBound);
    expect($lowBound)->toContain("-groupByBindings_1");
});

test('joined table and type reach the key', function () {
    $inner = cacheKey((new Book)->join("authors", "authors.id", "=", "books.author_id"));
    $left = cacheKey((new Book)->leftJoin("authors", "authors.id", "=", "books.author_id"));

    expect($inner)->toContain("-join_inner_authors_");
    expect($left)->toContain("-join_left_authors_");
    expect($left)->not->toEqual($inner);
});

// Two joins of one table on different columns share their type, their
// table and their cache tags. Only the hashed ON conditions separate them.
test('joins on the same table with different conditions key distinctly', function () {
    $byAuthor = cacheKey((new Book)->join("authors", "authors.id", "=", "books.author_id"));
    $byPublisher = cacheKey((new Book)->join("authors", "authors.id", "=", "books.publisher_id"));

    expect($byPublisher)->not->toEqual($byAuthor);
});

test('unjoined query key is unchanged', function () {
    $key = cacheKey((new Book)->where("title", "a"));

    expect($key)->toEndWith("-title_=_a");
});

test('distinct reaches the key', function () {
    $distinct = cacheKey((new Book)->distinct());
    $plain = cacheKey(new Book);

    expect($distinct)->toEndWith("-distinct");
    expect($plain)->not->toContain("-distinct");
});

test('distinct columns reach the key', function () {
    $key = cacheKey((new Book)->distinct("author_id"));

    expect($key)->toEndWith("-distinct_author_id");
});

test('unions reach the key', function () {
    $first = cacheKey((new Book)->where("id", 1)->union(DB::table("books")->where("id", 2)));
    $second = cacheKey((new Book)->where("id", 1)->union(DB::table("books")->where("id", 3)));

    expect($first)->toContain("-union_");
    expect($second)->not->toEqual($first);
});

test('union returns its own rows after another union was cached', function () {
    (new Book)->where("id", 1)->union(DB::table("books")->where("id", 2))->get();

    $books = (new Book)->where("id", 1)->union(DB::table("books")->where("id", 3))->get();
    $liveResults = (new UncachedBook)->where("id", 1)->union(DB::table("books")->where("id", 3))->get();

    expect($books->pluck("id")->sort()->values())->toEqual(
        $liveResults->pluck("id")->sort()->values(),
    );
});

test('union all keys distinctly from union', function () {
    $union = cacheKey((new Book)->where("id", 1)->union(DB::table("books")->where("id", 2)));
    $unionAll = cacheKey((new Book)->where("id", 1)->unionAll(DB::table("books")->where("id", 2)));

    expect($unionAll)->not->toEqual($union);
    expect($unionAll)->toContain("-union_all_");
});

// The backstop. Nothing names lock, indexHint, groupLimit, unionLimit,
// unionOffset or unionOrders, and each of them changes which rows come
// back. They reach the key through the unkeyed-property hash instead.
test('unkeyed query properties reach the key through the backstop hash', function () {
    $unlocked = cacheKey((new Book)->where("id", 1));
    $locked = cacheKey((new Book)->where("id", 1)->lockForUpdate());
    $shared = cacheKey((new Book)->where("id", 1)->sharedLock());

    expect($unlocked)->not->toContain("-q");
    expect($locked)->toContain("-q");
    expect($locked)->not->toEqual($unlocked);
    expect($shared)->not->toEqual($locked);
});

test('union ordering reaches the key through the backstop hash', function () {
    $ascending = cacheKey((new Book)
        ->where("id", 1)
        ->union(DB::table("books")->where("id", 2))
        ->orderBy("id"));
    $descending = cacheKey((new Book)
        ->where("id", 1)
        ->union(DB::table("books")->where("id", 2))
        ->orderBy("id", "desc"));

    expect($descending)->not->toEqual($ascending);
});

// Both property lists are closed sets. A member silently added to either
// one stops that property reaching the key, which is the exact failure
// this backstop exists to prevent, and no other test would notice.
test('the keyed and infrastructure property lists are pinned', function () {
    $constants = (new ReflectionClass(CacheKey::class))->getConstants();

    expect($constants["KEYED_QUERY_PROPERTIES"])->toBe([
            "beforeQueryCallbacks",
            "columns",
            "distinct",
            "from",
            "groups",
            "havings",
            "joins",
            "limit",
            "offset",
            "orders",
            "unions",
            "wheres",
        ]);
    expect($constants["UNKEYED_INFRASTRUCTURE_PROPERTIES"])->toBe([
            "bindings",
            "bitwiseOperators",
            "connection",
            "grammar",
            "operators",
            "processor",
            "useWritePdo",
        ]);
});

// Every property named in either list has to exist on the builder. A
// rename in Laravel would otherwise leave a live property unaccounted for
// while the list still looks complete.
test('every listed property exists on the query builder', function () {
    $constants = (new ReflectionClass(CacheKey::class))->getConstants();
    $builderProperties = array_keys(get_object_vars(DB::table("books")));

    foreach (array_merge(
        $constants["KEYED_QUERY_PROPERTIES"],
        $constants["UNKEYED_INFRASTRUCTURE_PROPERTIES"],
    ) as $property) {
        // A failure names the property CacheKey lists but the query builder no longer has.
        expect($builderProperties)->toContain($property);
    }
});

// The tests above name one column, one join or one union each, so the "_"
// that joins several of them is never written. These pin that separator,
// because a key that spells it differently is a different key, and every
// consumer's cache for such a query would go cold on upgrade.
test('several group by columns are joined by underscores', function () {
    $key = cacheKey((new Book)->groupBy("author_id", "publisher_id"));

    expect($key)->toEndWith("-groupBy_author_id_publisher_id");
});

test('several group by bindings are joined by underscores', function () {
    $key = cacheKey((new Book)->groupByRaw("author_id + ? + ?", [1, 2]));

    expect($key)->toEndWith("-groupByBindings_1_2");
});

test('several distinct columns are joined by underscores', function () {
    $key = cacheKey((new Book)->distinct("author_id", "publisher_id"));

    expect($key)->toEndWith("-distinct_author_id_publisher_id");
});

test('several joins are joined by underscores', function () {
    $key = cacheKey((new Book)
        ->join("authors", "authors.id", "=", "books.author_id")
        ->leftJoin("publishers", "publishers.id", "=", "books.publisher_id"));

    expect($key)->toMatch("/-join_inner_authors_[0-9a-f]{12}_left_publishers_[0-9a-f]{12}(-|$)/");
});

test('several unions are joined by underscores', function () {
    $key = cacheKey((new Book)
        ->where("id", 1)
        ->union(DB::table("books")->where("id", 2))
        ->unionAll(DB::table("books")->where("id", 3)));

    expect($key)->toMatch("/-union_[0-9a-f]{40}_all_[0-9a-f]{40}(-|$)/");
});
