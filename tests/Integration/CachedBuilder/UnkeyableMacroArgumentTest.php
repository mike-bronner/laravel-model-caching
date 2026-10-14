<?php

use GeneaLabs\LaravelModelCaching\CachedBuilder;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorOrder;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorWithFilteringBuilder;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\AuthorWithId;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\IntegerStatus;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;

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
]);

test('an array argument json cannot encode runs the call uncached', function (Closure $argument) {
    $authors = AuthorWithFilteringBuilder::query()->ignoring($argument())->orderBy("id")->get();

    expect($authors->pluck("id")->all())->toBe((new UncachedAuthor)->orderBy("id")->pluck("id")->all())
        ->and(modelCacheKeys())->toBe([]);
})->with([
    "a malformed string" => fn () => ["\xB1"],
    "not a number" => fn () => [NAN],
    "a resource" => fn () => [streamHolding("contents")],
    "a self-referencing array" => function () {
        $values = ["first"];
        $values[] = &$values;

        return $values;
    },
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
    $argument = ["text", 7, 2.5, true, false, null, $stringable, IntegerStatus::Active, ["key" => "nested"]];
    $method = new \ReflectionMethod(CachedBuilder::class, "macroKeyArguments");

    expect($method->invoke(Book::query(), [$argument]))->toBe(
        '["text",7,2.5,true,false,null,{},1,{"key":"nested"}]',
    );
});
