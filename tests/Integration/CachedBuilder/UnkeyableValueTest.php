<?php

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\SerializesWithoutItsHandle;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\SleepsWithChosenProperties;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\UncachedBook;
use Illuminate\Database\Eloquent\Model;

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

function selfReferencingValue(mixed $neighbour): object
{
    $value = (object) ["items" => [null, $neighbour]];
    $value->items[0] = &$value->items;

    return $value;
}

function printersHavingIdAbove(Author $author, array $value)
{
    return $author->printers()
        ->groupBy("printers.id")
        ->having("printers.id", ">", $value);
}

function booksHavingAuthorAbove(Model $model, array $value)
{
    return $model
        ->groupBy("author_id")
        ->having("author_id", ">", $value);
}
