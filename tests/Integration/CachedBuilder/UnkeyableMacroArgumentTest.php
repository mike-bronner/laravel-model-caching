<?php

use GeneaLabs\LaravelModelCaching\CachedBuilder;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorOrder;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorWithFilteringBuilder;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorWithId;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\IntegerStatus;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;

test('an argument applied outside the query never shares a cache entry', function (Closure $call, Closure $filterFor) {
    $first = $call("keepResultsWhere", $filterFor(1))->get();
    $second = $call("keepResultsWhere", $filterFor(2))->get();

    expect($first->pluck("id")->all())->toBe([1])
        ->and($second->pluck("id")->all())->toBe([2])
        ->and(modelCacheKeys())->toBe([]);
})->with([
    "forwarded to the wrapped builder" => fn (string $method, $argument) => AuthorWithFilteringBuilder::query()
        ->{$method}($argument),
    "through a local macro" => fn (string $method, $argument) => authorsThroughLocalMacro($method, $argument),
    "through a global macro" => fn (string $method, $argument) => authorsThroughGlobalMacro($method, $argument),
])->with([
    "a closure" => fn (int $id) => fn ($author) => $author->id === $id,
    "an object without __toString" => fn (int $id) => new AuthorWithId($id),
    "a closure nested in an array" => fn (int $id) => ["by id" => [fn ($author) => $author->id === $id]],
    "an object nested in an array" => fn (int $id) => ["by id" => [new AuthorWithId($id)]],
    "a closure nested three arrays deep" => fn (int $id) => ["a" => ["b" => ["c" => [fn ($author) => $author->id === $id]]]],
    "an object nested three arrays deep" => fn (int $id) => ["a" => ["b" => ["c" => [new AuthorWithId($id)]]]],
]);

test('unit enums inside an array argument never share a cache entry', function (Closure $call) {
    $newest = $call("orderedByFirstOf", [AuthorOrder::Newest])->get()->pluck("id");
    $oldest = $call("orderedByFirstOf", [AuthorOrder::Oldest])->get()->pluck("id");

    expect($newest->all())->toBe((new UncachedAuthor)->orderByDesc("id")->pluck("id")->all())
        ->and($oldest->all())->toBe((new UncachedAuthor)->orderBy("id")->pluck("id")->all())
        ->and(modelCacheKeys())->toBe([]);
})->with([
    "forwarded to the wrapped builder" => fn (string $method, $argument) => AuthorWithFilteringBuilder::query()
        ->{$method}($argument),
    "through a local macro" => fn (string $method, $argument) => authorsThroughLocalMacro($method, $argument),
    "through a global macro" => fn (string $method, $argument) => authorsThroughGlobalMacro($method, $argument),
]);

test('an array argument json cannot encode runs the call uncached', function (Closure $call, Closure $argument) {
    $authors = $call("ignoring", $argument())->orderBy("id")->get();

    expect($authors->pluck("id")->all())->toBe((new UncachedAuthor)->orderBy("id")->pluck("id")->all())
        ->and(modelCacheKeys())->toBe([]);
})->with([
    "forwarded to the wrapped builder" => fn (string $method, $argument) => AuthorWithFilteringBuilder::query()
        ->{$method}($argument),
    "through a local macro" => fn (string $method, $argument) => authorsThroughLocalMacro($method, $argument),
    "through a global macro" => fn (string $method, $argument) => authorsThroughGlobalMacro($method, $argument),
])->with([
    "a malformed string" => fn () => ["\xB1"],
    "not a number" => fn () => [NAN],
    "a resource" => fn () => [streamHolding("contents")],
    "a self-referencing array" => function () {
        $values = ["first"];
        $values[] = &$values;

        return $values;
    },
]);

test('an array argument is walked to the json depth limit and no further', function (Closure $call) {
    $call("ignoring", arrayNested(512, "deepest"))->get();

    expect(modelCacheEntryKeys())->toHaveCount(1);

    $authors = $call("ignoring", arrayNested(513, "deepest"))->orderBy("id")->get();

    expect($authors->pluck("id")->all())->toBe((new UncachedAuthor)->orderBy("id")->pluck("id")->all())
        ->and(modelCacheEntryKeys())->toHaveCount(1);
})->with([
    "forwarded to the wrapped builder" => fn (string $method, $argument) => AuthorWithFilteringBuilder::query()
        ->{$method}($argument),
    "through a local macro" => fn (string $method, $argument) => authorsThroughLocalMacro($method, $argument),
    "through a global macro" => fn (string $method, $argument) => authorsThroughGlobalMacro($method, $argument),
]);

test('stringable objects inside an array argument never share a cache entry', function (Closure $call, Closure $argument) {
    $call("ignoring", $argument("first"))->get();
    $call("ignoring", $argument("second"))->get();

    expect(modelCacheEntryKeys())->toHaveCount(2);
})->with([
    "forwarded to the wrapped builder" => fn (string $method, $argument) => AuthorWithFilteringBuilder::query()
        ->{$method}($argument),
    "through a local macro" => fn (string $method, $argument) => authorsThroughLocalMacro($method, $argument),
    "through a global macro" => fn (string $method, $argument) => authorsThroughGlobalMacro($method, $argument),
])->with([
    "a stringable with protected state" => fn (string $value) => [stringableHolding($value)],
    "a stringable nested three arrays deep" => fn (string $value) => ["a" => ["b" => ["c" => [stringableHolding($value)]]]],
]);

test('a keyable argument to the same fixture still caches', function () {
    AuthorWithFilteringBuilder::query()->whereNameIn(["a name"])->get();

    expect(modelCacheKeys())->not->toBe([]);
});

test('a forwarded write with a closure argument still flushes the cache', function () {
    $before = (new AuthorWithFilteringBuilder)->orderBy("id")->pluck("name");

    AuthorWithFilteringBuilder::query()->updateOrInsert(
        ["id" => 1],
        fn () => ["name" => "written through a closure"],
    );

    $live = (new UncachedAuthor)->orderBy("id")->pluck("name");

    expect($live)->not->toEqual($before)
        ->and((new AuthorWithFilteringBuilder)->orderBy("id")->pluck("name"))->toEqual($live);
});

test('an array of joinable values keeps its key spelling', function () {
    $stringable = new class {
        public function __toString(): string
        {
            return "stringable";
        }
    };
    $publicStringable = new class {
        public string $name = "public";

        public function __toString(): string
        {
            return "public stringable";
        }
    };
    $argument = ["text", 7, 2.5, true, false, null, $stringable, $publicStringable, IntegerStatus::Active, ["key" => "nested"]];
    $method = new ReflectionMethod(CachedBuilder::class, "macroKeyArguments");

    expect($method->invoke(Book::query(), [$argument]))->toBe(
        '["text",7,2.5,true,false,null,"stringable",{"name":"public"},1,{"key":"nested"}]',
    );
});

test('a builder argument applied outside the query keys by its own query', function (Closure $call, Closure $idsFor) {
    $first = $call("keepResultsIn", $idsFor(1))->get();
    $keysAfterFirst = modelCacheEntryKeys();
    $second = $call("keepResultsIn", $idsFor(2))->get();
    $keysAfterSecond = modelCacheEntryKeys();

    expect($first->pluck("id")->all())->toBe([1])
        ->and($second->pluck("id")->all())->toBe([2])
        ->and($keysAfterFirst)->toHaveCount(1)
        ->and($keysAfterSecond)->toHaveCount(2)
        ->and($keysAfterSecond)->toContain($keysAfterFirst[0]);

    expectServedFromCache(fn () => expect($call("keepResultsIn", $idsFor(1))->get()->pluck("id")->all())->toBe([1]));
})->with([
    "forwarded to the wrapped builder" => fn (string $method, $argument) => AuthorWithFilteringBuilder::query()
        ->{$method}($argument),
    "through a local macro" => fn (string $method, $argument) => authorsThroughLocalMacro($method, $argument),
    "through a global macro" => fn (string $method, $argument) => authorsThroughGlobalMacro($method, $argument),
])->with([
    "a query builder" => fn (int $id) => DB::table("authors")->where("id", $id),
    "an eloquent builder" => fn (int $id) => (new UncachedAuthor)->where("id", $id),
    "a relation" => fn (int $id) => (new UncachedBook)->forceFill(["author_id" => $id])->author(),
]);

test('builder arguments with the same SQL keep separate entries', function (Closure $first, Closure $second) {
    AuthorWithFilteringBuilder::query()->ignoringBuilder($first())->get();
    AuthorWithFilteringBuilder::query()->ignoringBuilder($first())->get();

    expect(modelCacheEntryKeys())->toHaveCount(1);

    AuthorWithFilteringBuilder::query()->ignoringBuilder($second())->get();

    expect(modelCacheEntryKeys())->toHaveCount(2);
})->with([
    "differing in eager loads" => [
        fn () => (new UncachedAuthor)->newQuery(),
        fn () => (new UncachedAuthor)->with("books"),
    ],
    "differing in a custom builder method" => [
        fn () => AuthorWithFilteringBuilder::query()->ignoring(["first"]),
        fn () => AuthorWithFilteringBuilder::query()->ignoring(["second"]),
    ],
    "relations differing in a custom builder method" => [
        fn () => bookAuthorRelationOver(AuthorWithFilteringBuilder::query()->ignoring(["first"])),
        fn () => bookAuthorRelationOver(AuthorWithFilteringBuilder::query()->ignoring(["second"])),
    ],
]);

test('a builder argument with an unkeyable binding runs the call uncached', function () {
    $authors = AuthorWithFilteringBuilder::query()
        ->ignoringBuilder(DB::table("authors")->where("name", streamHolding("a name")))
        ->orderBy("id")
        ->get();

    expect($authors->pluck("id")->all())->toBe((new UncachedAuthor)->orderBy("id")->pluck("id")->all())
        ->and(modelCacheKeys())->toBe([]);
});

test('a builder argument whose own macro holds a closure runs the call uncached', function () {
    $ids = fn (int $id) => AuthorWithFilteringBuilder::query()->keepResultsWhere(fn ($author) => $author->id === $id);

    AuthorWithFilteringBuilder::query()->keepResultsIn($ids(1))->get();
    AuthorWithFilteringBuilder::query()->keepResultsIn($ids(2))->get();

    expect(modelCacheKeys())->toBe([]);
});

test('a through relation keys the macro and custom builder arguments it forwards', function (Closure $call) {
    $call("ignoring", ["first"])->get();
    $call("ignoring", ["first"])->get();

    expect(modelCacheEntryKeys())->toHaveCount(1);

    $call("ignoring", ["second"])->get();

    expect(modelCacheEntryKeys())->toHaveCount(2);
})->with([
    "forwarded to the wrapped builder" => fn (string $method, $argument) => filteringAuthorsThroughBooks()->{$method}($argument),
    "through a local macro" => fn (string $method, $argument) => authorsThroughLocalMacro($method, $argument, filteringAuthorsThroughBooks()),
    "through a global macro" => fn (string $method, $argument) => authorsThroughGlobalMacro($method, $argument, filteringAuthorsThroughBooks()),
]);

test('a through relation runs a call with an unkeyable argument uncached', function (Closure $call, Closure $argument) {
    $authors = $call("keepResultsWhere", $argument())->get();

    expect($authors->pluck("id")->all())->toBe(array_fill(0, (new UncachedBook)->where("author_id", 1)->count(), 1))
        ->and(modelCacheKeys())->toBe([]);
})->with([
    "forwarded to the wrapped builder" => fn (string $method, $argument) => filteringAuthorsThroughBooks()->{$method}($argument),
    "through a local macro" => fn (string $method, $argument) => authorsThroughLocalMacro($method, $argument, filteringAuthorsThroughBooks()),
    "through a global macro" => fn (string $method, $argument) => authorsThroughGlobalMacro($method, $argument, filteringAuthorsThroughBooks()),
])->with([
    "a closure" => fn () => fn ($author) => true,
    "an object without __toString" => fn () => new AuthorWithId(1),
]);
