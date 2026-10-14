<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\SerializesWithoutItsHandle;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\SleepsWithChosenProperties;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedAuthor;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

test('a having value that cannot be serialized runs the query uncached', function () {
    $value = (object) ["filter" => fn () => true];
    $books = booksHavingAuthorAbove(new Book, [1, $value])->get();
    $liveResults = booksHavingAuthorAbove(new UncachedBook, [1, $value])->get();

    expect($books)->not->toBeEmpty()
        ->and($books->pluck("id"))->toEqual($liveResults->pluck("id"))
        ->and(modelCacheKeys())->toBe([]);
});

test('a having value holding a resource runs the query uncached', function (object $value) {
    $books = booksHavingAuthorAbove(new Book, [1, $value])->get();
    $liveResults = booksHavingAuthorAbove(new UncachedBook, [1, $value])->get();

    expect($books)->not->toBeEmpty()
        ->and($books->pluck("id"))->toEqual($liveResults->pluck("id"))
        ->and(modelCacheKeys())->toBe([]);
})->with([
    "an open stream" => fn () => (object) ["stream" => fopen("php://memory", "r")],
    "a closed stream" => fn () => (object) ["stream" => tap(fopen("php://memory", "r"), fclose(...))],
    "a stream in an array" => fn () => (object) ["streams" => [fopen("php://memory", "r")]],
    "a stream in a nested object" => fn () => (object) [
        "wrapper" => (object) ["stream" => fopen("php://memory", "r")],
    ],
    "a stream that __sleep() keeps" => fn () => new SleepsWithChosenProperties(["handle", "sleepingProperties"]),
    "a stream beside a self-referencing array" => fn () => selfReferencingValue(fopen("php://memory", "r")),
]);

test('a having value that serializes without its resource keeps its key', function (object $value) {
    $query = booksHavingAuthorAbove(new Book, [1, $value]);

    expect(cacheKey($query))->toContain($value::class . "-" . sha1(serialize($value)));

    assertCachedUnderTags($query, bookTags());
})->with([
    "__serialize()" => fn () => new SerializesWithoutItsHandle("first"),
    "__sleep()" => fn () => new SleepsWithChosenProperties(["sleepingProperties"]),
    "a self-referencing array" => fn () => selfReferencingValue("first"),
]);

test('an unkeyable value that the bindings replace keeps the key it had', function () {
    $query = (new Book)->whereBetween("id", [1, 3, (object) ["filter" => fn () => true]]);

    expect(cacheKey($query))->toBe(cacheKey((new Book)->whereBetween("id", [1, 3])));

    assertCachedUnderTags($query, bookTags());
});

test('a through relation with an unkeyable having value runs the query uncached', function () {
    $author = (new Author)->first();
    $keysBefore = collect(modelCacheKeys())->sort()->values()->all();
    $printers = printersHavingIdAbove($author, [0, (object) ["filter" => fn () => true]])->get();
    $livePrinters = printersHavingIdAbove($author, [0])->disableModelCaching()->get();

    expect($printers)->not->toBeEmpty()
        ->and($printers->pluck("id"))->toEqual($livePrinters->pluck("id"))
        ->and(collect(modelCacheKeys())->sort()->values()->all())->toBe($keysBefore);
});

test('a having value holding a self-referencing array returns its live rows', function () {
    $value = selfReferencingValue("first");
    $books = booksHavingAuthorAbove(new Book, [1, $value])->get();
    $liveResults = booksHavingAuthorAbove(new UncachedBook, [1, $value])->get();

    expect($books)->not->toBeEmpty()
        ->and($books->pluck("id"))->toEqual($liveResults->pluck("id"));
});

test('a where value holding a self-referencing array returns its live rows', function () {
    $value = selfReferencingValue("first");
    $books = (new Book)->where("author_id", ">", [1, $value])->get();
    $liveResults = (new UncachedBook)->where("author_id", ">", [1, $value])->get();

    expect($books)->not->toBeEmpty()
        ->and($books->pluck("id"))->toEqual($liveResults->pluck("id"));
});

test('an unexpected value error raised while keying the query is not swallowed', function () {
    $value = new class implements Stringable
    {
        public int $conversions = 0;

        public function __toString(): string
        {
            throw_if($this->conversions++ === 0, UnexpectedValueException::class, "first conversion failed");

            return "1";
        }
    };

    expect(fn () => (new Book)->where("author_id", ">", $value)->get())
        ->toThrow(UnexpectedValueException::class, "first conversion failed");
});

test('a bare resource binding runs the query uncached', function (Closure $query) {
    $books = $query(new Book, streamHolding("first"))->get();
    $liveResults = $query(new UncachedBook, streamHolding("first"))->get();

    expect($books)->not->toBeEmpty()
        ->and($books->toArray())->toEqual($liveResults->toArray())
        ->and(modelCacheKeys())->toBe([]);
})->with([
    "where" => fn (Model $model, $stream) => $model->where("title", "!=", $stream),
    "where raw" => fn (Model $model, $stream) => $model->whereRaw("title != ?", [$stream]),
    "where not in" => fn (Model $model, $stream) => $model->whereNotIn("title", [$stream]),
    "where row values" => fn (Model $model, $stream) => $model->whereRowValues(["title"], "!=", [$stream]),
    "having" => fn (Model $model, $stream) => $model
        ->groupBy("author_id")
        ->having("author_id", "!=", $stream),
    "from sub" => fn (Model $model, $stream) => $model->fromSub(
        fn ($query) => $query->from("books")->where("title", "!=", $stream),
        "books",
    ),
    "union" => fn (Model $model, $stream) => $model
        ->where("id", 0)
        ->union(DB::table("books")->where("title", "!=", $stream)),
    "before query" => fn (Model $model, $stream) => $model
        ->beforeQuery(fn ($query) => $query->where("title", "!=", $stream)),
    "eager load constraint" => fn (Model $model, $stream) => $model
        ->with(["author" => fn ($query) => $query->where("name", "!=", $stream)]),
    "join" => fn (Model $model, $stream) => $model
        ->select("books.*")
        ->join("authors", fn ($join) => $join
            ->on("authors.id", "=", "books.author_id")
            ->where("authors.name", "!=", $stream)),
    "group by" => fn (Model $model, $stream) => $model
        ->groupByRaw("author_id, ?", [$stream]),
]);

test('a before query callback that throws while the key is built runs the query uncached', function () {
    [$first, $second] = DB::table("authors")->orderBy("id")->limit(2)->pluck("name")->all();
    $query = fn (string $name) => (new Author)
        ->beforeQuery(throwsOnFirstCall(fn ($query) => $query->where("name", $name)));

    $query($first)->get();
    $authors = $query($second)->get();

    expect($authors->pluck("name")->all())->toBe([$second])
        ->and(modelCacheKeys())->toBe([]);
});

test('an eager load constraint that throws while the key is built runs the query uncached', function () {
    [$first, $second] = DB::table("books")->orderBy("id")->limit(2)->pluck("title")->all();
    $query = fn (string $title) => (new Author)
        ->with(["books" => throwsOnFirstCall(fn ($query) => $query->where("title", $title))]);
    $liveResults = (new UncachedAuthor)
        ->with(["books" => fn ($query) => $query->where("title", $second)])
        ->get();

    $query($first)->get();

    expect($query($second)->get()->toArray())->toEqual($liveResults->toArray());
});
