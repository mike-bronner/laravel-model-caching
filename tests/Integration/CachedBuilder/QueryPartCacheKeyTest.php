<?php

use GeneaLabs\LaravelModelCaching\CacheKey;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;
use Illuminate\Support\Facades\DB;

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

test('every listed property exists on the query builder', function () {
    $constants = (new ReflectionClass(CacheKey::class))->getConstants();
    $builderProperties = array_keys(get_object_vars(DB::table("books")));

    foreach (array_merge(
        $constants["KEYED_QUERY_PROPERTIES"],
        $constants["UNKEYED_INFRASTRUCTURE_PROPERTIES"],
    ) as $property) {
        expect($builderProperties)->toContain($property);
    }
});

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

test('a raw union ordering returns its own rows after another raw ordering was cached', function () {
    $ordered = fn ($model, string $expression) => $model
        ->where("id", "<", 4)
        ->union(DB::table("books")->where("id", ">", 6)->where("id", "<", 10))
        ->orderBy(DB::raw($expression));

    $byId = $ordered(new Book, "id")->get();
    $byTitle = $ordered(new Book, "title")->get();

    expect(cacheKey($ordered(new Book, "title")))->not->toEqual(cacheKey($ordered(new Book, "id")))
        ->and($byId->pluck("id"))->toEqual($ordered(new UncachedBook, "id")->get()->pluck("id"))
        ->and($byTitle->pluck("id"))->toEqual($ordered(new UncachedBook, "title")->get()->pluck("id"));
});

test('an index hint reaches the key through the backstop hash', function () {
    $books = (new Book)->where("author_id", 1)->get();
    $hinted = (new Book)->useIndex("books_author_id_index")->where("author_id", 1);

    expect(cacheKey($hinted))->toContain("-q")
        ->and(cacheKey($hinted))->not->toEqual(cacheKey((new Book)->where("author_id", 1)))
        ->and($hinted->get()->pluck("id"))->toEqual($books->pluck("id"));
});

test('raw column comparisons reach the key', function () {
    $sameAuthor = fn () => (new Book)->whereColumn(DB::raw("author_id"), "=", DB::raw("author_id"));
    $differentPublisher = fn () => (new Book)->whereColumn(DB::raw("author_id"), "=", DB::raw("publisher_id"));

    $allBooks = $sameAuthor()->get();
    $matchingBooks = $differentPublisher()->get();

    expect(cacheKey($differentPublisher()))->not->toEqual(cacheKey($sameAuthor()))
        ->and($allBooks->pluck("id"))->toEqual((new UncachedBook)->pluck("id"))
        ->and($matchingBooks->pluck("id"))
        ->toEqual((new UncachedBook)->whereColumn("author_id", "publisher_id")->pluck("id"));
});

test('a null having value reaches the key', function () {
    $havingNull = fn () => (new Book)->select("author_id")->groupBy("author_id")->having("author_id", "=", null);
    $havingOne = fn () => (new Book)->select("author_id")->groupBy("author_id")->having("author_id", "=", 1);

    $nullRows = $havingNull()->get();
    $oneRows = $havingOne()->get();

    expect(cacheKey($havingNull()))->not->toEqual(cacheKey($havingOne()))
        ->and($nullRows)->toBeEmpty()
        ->and($oneRows->pluck("author_id")->all())->toEqual([1]);
});

test('an array where value reaches the key', function () {
    $first = (new Book)->where("id", "=", [1])->get();
    $second = (new Book)->where("id", "=", [2])->get();

    expect(cacheKey((new Book)->where("id", "=", [2])))
        ->not->toEqual(cacheKey((new Book)->where("id", "=", [1])))
        ->and($first->pluck("id")->all())->toEqual([1])
        ->and($second->pluck("id")->all())->toEqual([2]);
});

test('an eloquent after query callback does not share the plain query cache entry', function () {
    (new Book)->where("id", 1)
        ->afterQuery(fn ($books) => $books->each(fn ($book) => $book->title = "after query"))
        ->get();

    expect((new Book)->where("id", 1)->get()->pluck("title")->all())->not->toBe(["after query"]);
});

test('a base query after query callback does not share the plain query cache entry', function () {
    $builder = (new Book)->where("id", 1);
    $builder->getQuery()->afterQuery(fn ($rows) => $rows->map(function ($row) {
        $row->title = "after query";

        return $row;
    }));
    $builder->get();

    expect((new Book)->where("id", 1)->get()->pluck("title")->all())->not->toBe(["after query"]);
});
