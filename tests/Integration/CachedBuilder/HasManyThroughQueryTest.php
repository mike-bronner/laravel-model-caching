<?php

use GeneaLabs\LaravelModelCaching\CachedHasManyThrough;
use GeneaLabs\LaravelModelCaching\CachedHasOneThrough;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Printer;
use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Publisher;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

dataset('has many through reads', hasManyThroughReads());

test('a has many through read is served from the cache', function (string $method, array $arguments) {
    $author = (new Author)->first();
    $arguments = throughReadArguments($author, $arguments);
    $cachedResult = throughReadSummary($author->printers()->{$method}(...$arguments));

    deletePrintersBehindTheCache($author);

    expect(throughReadSummary($author->printers()->{$method}(...$arguments)))
        ->toEqual($cachedResult)
        ->and(throughReadSummary($author->printers()->disableModelCaching()->{$method}(...$arguments)))
        ->not->toEqual($cachedResult);
})->with('has many through reads');

test('a has many through read with caching disabled reads the database', function (string $method, array $arguments) {
    $author = (new Author)->first();
    $arguments = throughReadArguments($author, $arguments);
    $cachedResult = throughReadSummary($author->printers()->{$method}(...$arguments));

    deletePrintersBehindTheCache($author);

    $result = throughReadSummary($author->printers()->disableModelCaching()->{$method}(...$arguments));

    expect($result)->not->toEqual($cachedResult)
        ->and($result)->toBeIn([null, 0, false, '']);
})->with('has many through reads');

test('each has many through read is cached under its own key', function () {
    $author = (new Author)->first();
    $reads = collect(hasManyThroughReads())
        ->map(fn (array $read) => [$read[0], throughReadArguments($author, $read[1])]);
    $freshResults = $reads->map(fn (array $read) => throughReadSummary(
        $author->printers()->disableModelCaching()->{$read[0]}(...$read[1]),
    ));

    $firstResults = $reads->map(fn (array $read) => throughReadSummary($author->printers()->{$read[0]}(...$read[1])));
    $secondResults = $reads->map(fn (array $read) => throughReadSummary($author->printers()->{$read[0]}(...$read[1])));

    expect($firstResults->all())->toEqual($freshResults->all())
        ->and($secondResults->all())->toEqual($freshResults->all());
});

test('a has many through cache hit fires the retrieved event for each model', function () {
    $author = (new Author)->first();
    $printers = $author->printers()->get();
    $author->printers()->first();
    $author->printers()->paginate(5);
    $author->printers()->pluck('printers.name');
    $firedCount = 0;

    Printer::retrieved(function () use (&$firedCount) {
        $firedCount++;
    });

    $author->printers()->get();

    expect($firedCount)->toBe($printers->count());

    $author->printers()->first();

    expect($firedCount)->toBe($printers->count() + 1);

    $author->printers()->paginate(5);

    expect($firedCount)->toBe($printers->count() + 1 + min(5, $printers->count()));

    $author->printers()->pluck('printers.name');

    expect($firedCount)->toBe($printers->count() + 1 + min(5, $printers->count()));
});

test('a has many through cache entry stored under another key is replaced', function () {
    $author = (new Author)->first();
    $relation = $author->printers();
    $key = sha1(cacheKey($relation));
    $tags = (new ReflectionMethod($relation, 'makeCacheTags'))->invoke($relation);

    $this->cache()
        ->tags($tags)
        ->forever($key, ['key' => 'a different query', 'value' => 'not the printers']);

    $printers = $author->printers()->get();

    expect($printers)->toBeInstanceOf(Collection::class)
        ->and($printers->pluck('id')->all())
        ->toEqual(DB::table('printers')
            ->join('books', 'books.id', '=', 'printers.book_id')
            ->where('books.author_id', $author->id)
            ->pluck('printers.id')
            ->all());
});

test('a through relation is not cached when model caching is disabled', function () {
    config(['laravel-model-caching.enabled' => false]);
    $author = (new Author)->first();

    expect($author->printers())
        ->toBeInstanceOf(HasManyThrough::class)
        ->not->toBeInstanceOf(CachedHasManyThrough::class)
        ->and($author->hasOneThrough(Printer::class, Book::class))
        ->toBeInstanceOf(HasOneThrough::class)
        ->not->toBeInstanceOf(CachedHasOneThrough::class);

    $printerCount = $author->printers()->count();
    deletePrintersBehindTheCache($author);

    expect($printerCount)->toBeGreaterThan(0)
        ->and($author->printers()->count())->toBe(0)
        ->and(modelCacheKeys())->toBe([]);
});

test('a has many through query keys its eager loads', function () {
    $author = Author::factory()->create();
    $book = Book::factory()->create(['author_id' => $author->id]);
    Printer::factory()->create(['book_id' => $book->id]);
    $author->printers()->get();

    $printer = $author->printers()
        ->with('book')
        ->get()
        ->first();

    expect($printer->relationLoaded('book'))->toBeTrue();
});

test('a write to an eager loaded model clears a has many through query that loads it', function () {
    $author = Author::factory()->create();
    $book = Book::factory()->create(['author_id' => $author->id]);
    Printer::factory()->create(['book_id' => $book->id]);
    $readTitle = fn () => $author->printers()
        ->with('book')
        ->get()
        ->first()
        ->book
        ->title;
    $readTitle();
    $book->update(['title' => 'Changed Title']);

    expect($readTitle())->toBe('Changed Title');
});

test('a write to a nested eager loaded model clears a has many through query that loads it', function () {
    $author = Author::factory()->create();
    $publisher = Publisher::factory()->create();
    $book = Book::factory()->create(['author_id' => $author->id, 'publisher_id' => $publisher->id]);
    Printer::factory()->create(['book_id' => $book->id]);
    $readName = fn () => $author->printers()
        ->with('book.publisher')
        ->get()
        ->first()
        ->book
        ->publisher
        ->name;
    $readName();
    $publisher->update(['name' => 'Changed Name']);

    expect($readName())->toBe('Changed Name');
});
