<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\KeepsItsNamePrivate;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\StringStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

test('from sub queries that differ only in a private stringable binding keep their own rows', function () {
    expectPrivateBindingsToKeepTheirOwnRows(
        fn (Model $model, Stringable $name) => $model->fromSub(
            fn ($query) => $query->from("authors")->where("name", $name),
            "authors",
        ),
        firstTwoDistinctValues("authors", "name"),
    );
});

test('unions that differ only in a private stringable binding keep their own rows', function () {
    expectPrivateBindingsToKeepTheirOwnRows(
        fn (Model $model, Stringable $name) => $model
            ->where("id", 0)
            ->union(DB::table("authors")->where("name", $name)),
        firstTwoDistinctValues("authors", "name"),
    );
});

test('before query callbacks that differ only in a private stringable binding keep their own rows', function () {
    expectPrivateBindingsToKeepTheirOwnRows(
        fn (Model $model, Stringable $name) => $model
            ->beforeQuery(fn ($query) => $query->where("name", $name)),
        firstTwoDistinctValues("authors", "name"),
    );
});

test('eager load constraints that differ only in a private stringable binding keep their own rows', function () {
    expectPrivateBindingsToKeepTheirOwnRows(
        fn (Model $model, Stringable $title) => $model
            ->with(["books" => fn ($query) => $query->where("title", $title)]),
        firstTwoDistinctValues("books", "title"),
    );
});

test('where has constraints that differ only in a private stringable binding keep their own rows', function () {
    expectPrivateBindingsToKeepTheirOwnRows(
        fn (Model $model, Stringable $title) => $model
            ->whereHas("books", fn ($query) => $query->where("title", $title)),
        firstTwoDistinctValues("books", "title"),
    );
});

test('from sub queries that differ only in a stringable without properties keep their own rows', function () {
    [$first, $second] = firstTwoDistinctValues("authors", "name");
    $alice = new class implements Stringable
    {
        public static string $name = "";

        public function __toString(): string
        {
            return self::$name;
        }
    };
    $bob = new class implements Stringable
    {
        public static string $name = "";

        public function __toString(): string
        {
            return self::$name;
        }
    };
    $alice::$name = $first;
    $bob::$name = $second;

    expectBindingsToKeepTheirOwnRows(
        fn (Model $model, Stringable $name) => $model->fromSub(
            fn ($query) => $query->from("authors")->where("name", $name),
            "authors",
        ),
        [$alice, $bob],
    );
});

test('a from sub binding with private state and no string form is not keyed', function () {
    $query = (new Author)->fromSub(
        fn ($query) => $query->from("authors")->where("name", new class
        {
            private string $name = "Alice";
        }),
        "authors",
    );

    expect(cacheKey($query))->toBe("");
});

test('a from sub binding that keys correctly today keeps its key', function (mixed $value) {
    $query = (new Author)->fromSub(fn ($query) => $query->from("authors")->where("name", $value), "authors");
    $baseQuery = $query->getQuery();
    $from = $baseQuery->from->getValue($baseQuery->getGrammar());
    $bindings = $baseQuery->getRawBindings()["from"];
    $encoded = json_encode($bindings);

    expect(cacheKey($query))->toContain(Str::slug("sub-" . sha1($from . ($encoded === false ? serialize($bindings) : $encoded))));
})->with([
    "a string" => "Alice",
    "a stringable with public state" => fn () => new class ("Alice") implements Stringable
    {
        public function __construct(public string $name)
        {
        }

        public function __toString(): string
        {
            return $this->name;
        }
    },
    "a json serializable stringable" => fn () => Str::of("Alice"),
    "a json serializable stringable with private state" => fn () => new class ("Alice") implements JsonSerializable, Stringable
    {
        public function __construct(private string $name)
        {
        }

        public function jsonSerialize(): array
        {
            return ["label" => $this->name];
        }

        public function __toString(): string
        {
            return $this->name;
        }
    },
    "a carbon date" => fn () => Carbon::parse("2026-10-13 12:00:00"),
    "a date time" => fn () => new DateTime("2026-10-13 12:00:00"),
    "a date time immutable" => fn () => new DateTimeImmutable("2026-10-13 12:00:00"),
    "a backed enum" => fn () => StringStatus::Active,
]);

function expectPrivateBindingsToKeepTheirOwnRows(Closure $query, array $values): void
{
    expectBindingsToKeepTheirOwnRows(
        $query,
        collect($values)
            ->map(fn (string $value) => new KeepsItsNamePrivate($value))
            ->all(),
    );
}

function expectBindingsToKeepTheirOwnRows(Closure $query, array $bindings): void
{
    [$first, $second] = $bindings;
    $liveResults = $query(new UncachedAuthor, $second)->get()->toArray();
    $firstKey = cacheKey($query(new Author, $first));

    expect($firstKey)->not->toBe("")
        ->and($firstKey)->not->toBe(cacheKey($query(new Author, $second)));

    $query(new Author, $first)->get();

    expect($liveResults)->not->toBeEmpty()
        ->and($query(new Author, $second)->get()->toArray())->toEqual($liveResults);
}

function firstTwoDistinctValues(string $table, string $column): array
{
    $values = DB::table($table)
        ->orderBy("id")
        ->pluck($column)
        ->unique()
        ->take(2)
        ->values()
        ->all();

    expect($values)->toHaveCount(2);

    return $values;
}
